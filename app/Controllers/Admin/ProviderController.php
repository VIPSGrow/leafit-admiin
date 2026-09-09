<?php

namespace App\Controllers\Admin;

use App\Models\Cash_collection_model;
use App\Models\Orders_model;
use App\Models\Partners_model;
use App\Models\ProviderShifts_model;
use App\Models\ProviderLeaves_model;
use App\Models\Users_model;
use App\Models\Settlement_model;
use App\Models\Seo_model;
use App\Services\PartnerService;
use App\Services\Provider\SlotSettingsService;
use Config\ApiResponseAndNotificationStrings;
use IonAuth\Models\IonAuthModel;
use App\Services\utility\SlugService;
use Exception;

class ProviderController extends Admin
{
    protected Users_model $users;
    protected Cash_collection_model $cash_collection;
    protected Settlement_model $settle_commission;
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected Seo_model $seoModel;
    protected PartnerService $partnerService;
    protected SlotSettingsService $slotSettingsService;
    protected ProviderShifts_model $providerShifts;
    protected ProviderLeaves_model $providerLeaves;
    protected $defaultLanguage;
    protected $slugService;

    public $partner, $validation, $db, $ionAuth, $creator_id;
    public function __construct()
    {
        parent::__construct();
        $this->partner = new Partners_model();
        $this->users = new Users_model();
        $this->cash_collection = new Cash_collection_model();
        $this->settle_commission = new Settlement_model();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        $this->trans = new ApiResponseAndNotificationStrings();
        $this->seoModel = new Seo_model();
        $this->partnerService = new PartnerService();
        $this->slotSettingsService = new SlotSettingsService();
        $this->providerShifts = new ProviderShifts_model();
        $this->providerLeaves = new ProviderLeaves_model();
        $this->defaultLanguage = get_default_language();
        $this->slugService = new SlugService();
        helper('ResponceServices');
    }
    public function index()
    {
        helper('function');
        setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partners');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }
    public function add_partner()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('add_providers', 'Add Provider') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'add_partner');
                $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId])) ? fetch_details('partner_details', ['partner_id' => $this->userId])[0] : [];
                $partner_timings = !empty(fetch_details('partner_timings', ['partner_id' => $this->userId])) ? fetch_details('partner_timings', ['partner_id' => $this->userId]) : [];
                $this->data['data'] = fetch_details('users', ['id' => $this->userId])[0];
                $settings = get_settings('general_settings', true);
                if (empty($settings)) {
                    $_SESSION['toastMessage'] = labels(FIRST_ADD_CURRENCY_AND_BASIC_DETAILS_IN_GENERAL_SETTINGS, 'Please first add currency and basic details in general settings');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }
                $this->data['currency'] = $settings['currency'];
                $this->data['allow_pre_booking_chat'] = $settings['allow_pre_booking_chat'] ?? 0;
                $this->data['allow_post_booking_chat'] = $settings['allow_post_booking_chat'] ?? 0;
                $this->data['max_serviceable_distance_type'] = $settings['max_serviceable_distance_type'] ?? 'global';
                $this->data['partner_details'] = $partner_details;
                $this->data['partner_timings'] = $partner_timings;
                $this->data['city_name'] = fetch_details('cities', [], ['id', 'name']);

                // Fetch subscriptions with translations for current language
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1]);
                $this->data['subscription_details'] = $subscription_details;

                // Prepare country code data for the view (for new partners, use default)
                $country_code_data = prepare_country_code_data('');
                $this->data['country_codes'] = $country_code_data['country_codes'];
                $this->data['selected_country_code'] = $country_code_data['selected_country_code'];

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                $cfDefs = $this->loadCustomFieldDefinitions($languages);
                $this->data['documents_custom_fields'] = $cfDefs['documents'];
                $this->data['bank_details_custom_fields'] = $cfDefs['bank_details'];
                $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - add_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 20;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Get current language for translations
            $current_language = get_current_language();

            print_r(json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order, [], 'pd.id', [], [], null, $current_language)));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function view_partner()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $data = fetch_details('partner_details', ['partner_id' => $partner_id]);
            if (empty($data)) {
                return redirect('admin/partners');
            }
            $settings = get_settings('general_settings', true);
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $partner_details = $data[0];
            $user_details = fetch_details('users', ['id' => $partner_id])[0];
            setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'view_partner');
            $this->data['partner_details'] = $partner_details;
            $this->data['personal_details'] = $user_details;
            return view('backend/admin/template', $this->data);
        } catch (Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - view_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function edit_partner()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $data = fetch_details('partner_details', ['partner_id' => $partner_id]);
            if (empty($data)) {
                return redirect('admin/partners');
            }
            $partner_details = $data[0];
            $user_details = fetch_details('users', ['id' => $partner_id])[0];
            $this->data['provider_locations'] = (new \App\Models\ProviderLocationsModel())
                ->where('provider_id', (int) $partner_id)
                ->orderBy('is_default', 'DESC')
                ->orderBy('id', 'ASC')
                ->findAll();
            $settings = get_settings('general_settings', true);
            $partner_timings = fetch_details('partner_timings', ['partner_id' => $partner_id], '', '', '', '', 'ASC');

            // Load multi-shift schedule grouped by day for prefilling the working hours UI.
            $provider_shifts_by_day = $this->providerShifts->tableExists()
                ? $this->providerShifts->getByPartnerGroupedByDay((int) $partner_id)
                : [];
            $this->data['currency'] = $settings['currency'];

            setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'edit_partner');

            // fetch languages early so loadCustomFieldDefinitions can use them
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $cfDefs = $this->loadCustomFieldDefinitions($languages);

            $allCfIds = array_map(static fn($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));
            $customFieldValues = $this->getPartnerCustomFieldValuesById($partner_id, $allCfIds);

            $fileService = service('fileService');

            $partner_details['banner'] = $fileService->url($partner_details['banner'], 'banner');

            foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                if ($cfField['field_type'] !== 'file') {
                    continue;
                }
                $rawVal = $customFieldValues[$cfField['id']] ?? '';
                if ($rawVal === '') {
                    continue;
                }
                $customFieldValues[$cfField['id']] = $fileService->url($rawVal, 'custom_fields');
            }

            $decodedImages = !empty($partner_details['other_images'])
                ? (json_decode($partner_details['other_images'], true) ?: [])
                : [];
            $partner_details['other_images'] = array_map(
                fn($p) => $fileService->url($p, 'partner'),
                $decodedImages
            );

            $user_details['image'] = $fileService->url($user_details['image'], 'profile');
            $this->data['partner_details'] = $partner_details;
            $this->data['custom_field_values'] = $customFieldValues;
            $this->data['personal_details'] = $user_details;
            $this->data['partner_timings'] = $partner_timings;
            $this->data['provider_shifts_by_day'] = $provider_shifts_by_day;
            $this->data['slot_settings'] = $this->slotSettingsService->find((int) $partner_id);
            $this->data['allow_pre_booking_chat'] = ($settings['allow_pre_booking_chat']) ?? 0;
            $this->data['allow_post_booking_chat'] = ($settings['allow_post_booking_chat']) ?? 0;
            $this->data['max_serviceable_distance_type'] = $settings['max_serviceable_distance_type'] ?? 'global';

            // First get the active partner subscription record
            $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);

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

            $symbol = get_currency();
            $this->data['currency'] = $symbol;
            $this->data['active_subscription_details'] = $active_subscription_details;
            $this->data['partner_id'] = $partner_id;

            // Fetch available subscriptions with translations for current language
            $subscriptionModel = new \App\Models\Subscription_model();
            $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1]);
            $this->data['subscription_details'] = $subscription_details;

            $this->seoModel->setTableContext('providers');
            $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($partner_id, 'full');
            $this->data['partner_seo_settings'] = $seo_settings;

            // Prepare country code data for the view
            $user_country_code = $user_details['country_code'] ?? '';
            $country_code_data = prepare_country_code_data($user_country_code);
            $this->data['country_codes'] = $country_code_data['country_codes'];
            $this->data['selected_country_code'] = $country_code_data['selected_country_code'];

            // ($languages is already fetched and set above via loadCustomFieldDefinitions)

            // Load translated partner details using PartnerService
            $partnerService = new PartnerService();
            $translatedData = $partnerService->getPartnerWithTranslations($partner_id);

            if ($translatedData['success']) {
                // Merge translated data with partner details for each language
                $mergedPartnerDetails = $partner_details;

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    if (isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Create language-specific partner details
                        $mergedPartnerDetails['translated_' . $languageCode] = [
                            'username' => $isDefault ? $user_details['username'] : ($translation['username'] ?? ''),
                            'company_name' => $translation['company_name'] ?? $partner_details['company_name'],
                            'about' => $translation['about'] ?? $partner_details['about'],
                            'long_description' => $translation['long_description'] ?? $partner_details['long_description']
                        ];
                    } else {
                        // If no translation exists, create with default values
                        $mergedPartnerDetails['translated_' . $languageCode] = [
                            'username' => $isDefault ? $user_details['username'] : '',
                            'company_name' => $isDefault ? $partner_details['company_name'] : '',
                            'about' => $isDefault ? $partner_details['about'] : '',
                            'long_description' => $isDefault ? $partner_details['long_description'] : ''
                        ];
                    }
                }

                $this->data['partner_details'] = $mergedPartnerDetails;
            }

            // Load SEO translations and merge with main SEO settings
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($partner_id);

            // Always merge SEO translations with main SEO settings (even if no translations exist)
            $mergedSeoSettings = $seo_settings;

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefault = $language['is_default'] == 1;

                // Find SEO translation for this language
                $seoTranslation = null;
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        if ($translation['language_code'] === $languageCode) {
                            $seoTranslation = $translation;
                            break;
                        }
                    }
                }

                if ($seoTranslation) {
                    // Create language-specific SEO settings from translation
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $seoTranslation['seo_title'] ?? '',
                        'description' => $seoTranslation['seo_description'] ?? '',
                        'keywords' => $seoTranslation['seo_keywords'] ?? '',
                        'schema_markup' => $seoTranslation['seo_schema_markup'] ?? ''
                    ];
                } else {
                    // If no SEO translation exists, use base table data for default language, empty for others
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $isDefault ? ($seo_settings['title'] ?? '') : '',
                        'description' => $isDefault ? ($seo_settings['description'] ?? '') : '',
                        'keywords' => $isDefault ? ($seo_settings['keywords'] ?? '') : '',
                        'schema_markup' => $isDefault ? ($seo_settings['schema_markup'] ?? '') : ''
                    ];
                }
            }

            $this->data['partner_seo_settings'] = $mergedSeoSettings;

            // Pass custom-field definitions and labels to the view.
            $this->data['documents_custom_fields'] = $cfDefs['documents'];
            $this->data['bank_details_custom_fields'] = $cfDefs['bank_details'];
            $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - edit_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function insert_partner()
    {
        try {
            helper('function');

            //Validate inputs
            $validationRules = $this->getValidationRules();

            // Dynamically add validation rules for all visible + required file-type custom fields.
            // This replaces the old hardcoded passport/national_id/address_id verification checks.
            $visibleFileCustomFields = $this->getVisibleFileCustomFields();
            foreach ($visibleFileCustomFields as $cfRow) {
                if (!$cfRow['required']) {
                    continue;
                }
                $cfId = (int) $cfRow['id'];
                $inputName = 'cf_' . $cfId;
                $fileConfig = $cfRow['file_config'];
                $maxSizeKb = (int) (($fileConfig['max_size_mb'] ?? 2) * 1024);
                $mimeTypes = $this->extensionsToMimeTypes($fileConfig['allowed_types'] ?? []);
                $label = 'Custom Field ' . $cfId;

                $rules = "uploaded[{$inputName}]|max_size[{$inputName},{$maxSizeKb}]";
                $errors = [
                    'uploaded' => labels('please_upload_a_valid_custom_field_document', "Please upload a valid document"),
                    'max_size' => labels('custom_field_file_size_exceeds_limit', "File size should not exceed " . ($fileConfig['max_size_mb'] ?? 2) . "MB"),
                ];
                if (!empty($mimeTypes)) {
                    $rules .= "|mime_in[{$inputName}," . implode(',', $mimeTypes) . "]";
                    $errors['mime_in'] = labels('custom_field_must_be_a_valid_file', "File must be a valid file type");
                }
                $validationRules[$inputName] = [
                    'rules' => $rules,
                    'errors' => $errors,
                ];
            }

            $this->validation->setRules($validationRules);
            if (!$this->validation->withRequest($this->request)->run()) {
                return JsonError($this->validation->getErrors());
            }

            $result = (new \App\Services\Provider\ProviderCreateService())
                ->create($this->request, $visibleFileCustomFields);

            if (!empty($result['error'])) {
                return JsonError($result['message']);
            }

            return JsonSuccess(labels(DATA_SAVED_SUCCESSFULLY, "Data Saved Successfully"), ['partner_id' => $result['partner_id']]);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - insert_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function deactivate_partner()
    {
        try {
            $partner_id = $this->request->getPost('partner_id');

            if ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && !empty($partner_id) && (int) $partner_id === 50) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }

            $partner_details = fetch_details('users', ['id' => $partner_id])[0];
            $operation = $this->ionAuth->deactivate($partner_id);
            if ($operation) {
                return JsonSuccess(labels(SUCCESSFULLY_DISABLED, "successfully disabled"));
            } else {
                return JsonError(labels(UNSUCCESSFUL_ATTEMPT_TO_DISABLE_THE_USER, "unsuccessful attempt to disable the user"));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - deactivate_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function activate_partner()
    {
        try {
            $partner_id = $this->request->getPost('partner_id');
            if ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && !empty($partner_id) && (int) $partner_id === 50) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }
            $operation = $this->ionAuth->activate($partner_id);
            if ($operation) {
                return JsonSuccess(labels(SUCCESSFULLY_ACTIVATED, "successfully activated"));
            } else {
                return JsonError(labels(UNSUCCESSFUL_ATTEMPT_TO_DISABLE_THE_USER, "unsuccessful attempt to disable the user"));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - activate_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function approve_partner()
    {
        try {
            try {
                $partner_id = $this->request->getPost('partner_id');
                if ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && !empty($partner_id) && (int) $partner_id === 50) {
                    return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
                }
                $builder = $this->db->table('partner_details');
                $partner_approval = $builder->set('is_approved', 1)->where('partner_id', $partner_id)->update();

                // Send notifications when partner is approved
                if ($partner_approval) {
                    $notificationContext = [
                        'provider_id' => $partner_id
                    ];

                    try {
                        queue_notification_service(
                            eventType: 'provider_approved',
                            recipients: ['user_id' => $partner_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                            ]
                        );
                        // log_message('info', '[PROVIDER_APPROVED] Notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the approval process
                        // log_message('error', '[PROVIDER_APPROVED] Notification error: ' . $notificationError->getMessage());
                        log_message('error', '[PROVIDER_APPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                    }
                    return JsonSuccess(labels(PROVIDER_APPROVED, "Provider approved"), [$partner_approval]);
                } else {
                    return JsonSuccess(labels(COULD_NOT_APPROVE_PROVIDER, "Could not approve provider"));
                }
            } catch (\Exception $th) {

                return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - approve_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function disapprove_partner()
    {
        try {
            try {
                $partner_id = $this->request->getPost('partner_id');
                if ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && !empty($partner_id) && (int) $partner_id === 50) {
                    return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
                }
                $builder = $this->db->table('partner_details');
                $partner_approval = $builder->set('is_approved', 0)->where('partner_id', $partner_id)->update();

                // Send notifications when partner is disapproved
                if ($partner_approval) {
                    $notificationContext = [
                        'provider_id' => $partner_id
                    ];

                    try {
                        queue_notification_service(
                            eventType: 'provider_disapproved',
                            recipients: ['user_id' => $partner_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'],
                                'type' => 'provider_request_status',
                                'data' => ['status' => 'reject', 'type_id' => (string) $partner_id]
                            ]
                        );
                    } catch (\Throwable $notificationError) {
                        log_message('error', '[PROVIDER_DISAPPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    // For SMS template testing we now send a direct, one-off SMS using NotificationService.
                    // This uses the `payment_reminder` SMS template and bypasses notification preferences
                    // so that the test SMS is always delivered, even if the user has disabled SMS.
                    // The template variables are hard-coded here for simple, repeatable testing.
                    // try {
                    //     $notificationService = new NotificationService();

                    //     // Hard-coded test context for the payment_reminder SMS template.
                    //     // You can safely change these values while testing different messages.
                    //     $paymentReminderContext = [
                    //         'customer_name'  => 'Test Customer',
                    //         'service_name'   => 'Test Service',
                    //         'provider_id'    => $partner_id,
                    //     ];

                    //     // Send SMS directly without using the queue.
                    //     // - eventType `payment_reminder` must match `sms_templates.type`.
                    //     // - channels => ['sms'] restricts this to SMS only.
                    //     // - user_ids + bypass_preference_check => true ensures preferences are bypassed
                    //     //   inside NotificationService::sendToMultipleRecipients() for SMS.
                    //     $notificationService->send(
                    //         'payment_reminder',
                    //         ['user_id' => (int)$partner_id],
                    //         $paymentReminderContext,
                    //         [
                    //             'channels'                => ['sms'],
                    //             'language'                => $this->defaultLanguage,
                    //             'user_ids'                => [(int)$partner_id],
                    //             'bypass_preference_check' => true,
                    //         ]
                    //     );
                    // } catch (\Throwable $notificationError) {
                    //     // Log error but don't fail the disapproval process
                    //     log_message('error', '[PROVIDER_DISAPPROVED_TEST_SMS] Notification error trace: ' . $notificationError->getTraceAsString());
                    // }

                    return JsonSuccess(labels(PROVIDER_DISAPPROVED, "Provider disapproved"), [$partner_approval]);
                } else {
                    return JsonSuccess(labels(COULD_NOT_DISAPPROVE_PROVIDER, "Could not disapprove provider"), [$partner_approval]);
                }
            } catch (\Exception $th) {

                return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - disapprove_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function delete_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = (new \App\Services\utility\PermissionService())->can((int) $this->creator_id, 'delete', 'partner');
            if (!$permission) {
                return JsonNoPermission();
            }

            $partner_id = (int) $this->request->getPost('partner_id');

            $result = (new \App\Services\Provider\ProviderDeleteService())->delete($partner_id);

            if ($result['userExisted']) {
                $message = $result['userDeleted']
                    ? labels(PROVIDER_REMOVED, 'Provider Removed')
                    : labels(COULD_NOT_DELETE_PROVIDER, 'Could not Delete provider');
                return JsonSuccess($message, [$result['userDeleted']]);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - delete_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function banking_details()
    {
        try {
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $db = \Config\Database::connect();

            $cityRow = fetch_details('users', ['id' => $partner_id], 'city');
            $city = !empty($cityRow) ? ($cityRow[0]['city'] ?? '') : '';

            $db = \Config\Database::connect();
            $customFields = $db->table('custom_fields')
                ->select(['id', 'field_label', 'field_group'])
                ->whereIn('field_group', ['documents', 'bank_details'])
                ->where('visible', 1)
                ->orderBy('sort_order', 'ASC')
                ->get()
                ->getResultArray();

            $cfIds = array_column($customFields, 'id');
            $valuesById = !empty($cfIds) ? $this->getPartnerCustomFieldValuesById((int) $partner_id, array_map('intval', $cfIds)) : [];

            $row = [
                'partner_id' => (int) $partner_id,
                'name' => $city,
            ];
            foreach ($customFields as $cf) {
                $label = $cf['field_label'] ?? ('Custom Field ' . $cf['id']);
                $row[$label] = $valuesById[(int) $cf['id']] ?? '';
            }

            $total = 1;
            $rows = [$row];
            $bulkData['total'] = $total;
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - banking_details()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function update_partner()
    {
        try {
            helper('function');

            if (empty($_POST)) {
                return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
            }

            // Set validation rules for non-translated fields only
            $validationRules = [
                'email' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_EMAIL, "Please enter providers email"),]],
                'address' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_ADDRESS, "Please enter address"),]],
                'type' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_PROVIDERS_TYPE, "Please select providers type"),]],
                'visiting_charges' => ["rules" => 'required|numeric', "errors" => ["required" => labels(PLEASE_ENTER_VISITING_CHARGES, "Please enter visiting charges"), "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_VISITING_CHARGES, "Please enter numeric value for visiting charges")]],
                'advance_booking_days' => ["rules" => 'required|numeric|greater_than_equal_to[1]', "errors" => ["required" => labels(PLEASE_ENTER_ADVANCE_BOOKING_DAYS, "Please enter advance booking days"), "numeric" => labels(PLEASE_ENTER_NUMERIC_ADVANCE_BOOKING_DAYS, "Please enter numeric advance booking days"), "greater_than_equal_to" => labels(ADVANCE_BOOKING_DAYS_MUST_BE_AT_LEAST_1, "Advance booking days must be at least 1")]],
                'start_time' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_WORKING_DAYS, "Please enter providers working days"),]],
                'end_time' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_WORKING_PROPERLY, "Please enter providers working properly"),]],
                'provider_slug' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_SLUG, "Please enter providers slug"),]],
            ];

            $this->validation->setRules($validationRules);
            if (!$this->validation->withRequest($this->request)->run()) {
                return JsonError($this->validation->getErrors());
            }

            $visibleFileCustomFields = $this->getVisibleFileCustomFields();

            $result = (new \App\Services\Provider\ProviderUpdateService())
                ->update($this->request, $visibleFileCustomFields);

            if (!empty($result['error'])) {
                return JsonError($result['message']);
            }

            return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), [], ['redirect_url' => base_url('admin/partners')]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - update_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function partner_company_information()
    {
        try {
            helper('function');
            $uri = service('uri');
            helper('function');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Get current language code to ensure translations use the selected language
            // This ensures the model uses the currently selected language instead of default
            $currentLanguageCode = get_current_language();

            // Pass the current language code to the model so it uses the correct translations
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id], 'pd.id', [], [], null, $currentLanguageCode)));

            // Add verification status variables for conditional display of ID fields
            $settings = get_settings('general_settings', true);
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
            $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
            $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
            $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
            $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;
            $this->data['max_serviceable_distance_type'] = $settings['max_serviceable_distance_type'] ?? 'global';

            $this->data['slot_settings'] = $this->slotSettingsService->find((int) $partner_id);

            // Load custom field definitions + values for this partner so the view can
            // render the documents/bank-details groups (these replace the fixed columns).
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $cfDefs = $this->loadCustomFieldDefinitions($languages);
            $this->data['documents_custom_fields'] = $cfDefs['documents'];
            $this->data['bank_details_custom_fields'] = $cfDefs['bank_details'];
            $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

            $allCfIds = array_map(static fn($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));
            $customFieldValues = $this->getPartnerCustomFieldValuesById((int) $partner_id, $allCfIds);

            $fileService = service('fileService');
            foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                if ($cfField['field_type'] !== 'file') {
                    continue;
                }
                $rawVal = $customFieldValues[$cfField['id']] ?? '';
                if ($rawVal === '') {
                    continue;
                }
                $customFieldValues[$cfField['id']] = $fileService->url($rawVal, 'custom_fields');
            }
            $this->data['custom_field_values'] = $customFieldValues;

            // Resolve provider image, banner, and other images server-side so the view
            // contains no file-resolution or disk-aware logic.
            $userRow = fetch_details('users', ['id' => $partner_id], ['image']);
            $partnerRow = fetch_details('partner_details', ['partner_id' => $partner_id], ['banner', 'other_images']);

            $this->data['logo_url'] = $fileService->url($userRow[0]['image'] ?? '', 'profile');
            $this->data['banner_url'] = $fileService->url($partnerRow[0]['banner'] ?? '', 'banner');

            $otherRaw = !empty($partnerRow[0]['other_images'])
                ? (json_decode($partnerRow[0]['other_images'], true) ?: [])
                : [];
            $this->data['other_image_urls'] = array_map(
                fn($p) => $fileService->url($p, 'partner'),
                is_array($otherRaw) ? $otherRaw : []
            );

            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_company_information', 'Provider Company Information') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_company_information');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_company_information()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function duplicate()
    {
        try {
            // Check if user has create permission for duplicating providers
            $permission = (new \App\Services\utility\PermissionService())->can((int) $this->creator_id, 'create', 'partner');
            if (!$permission) {
                // Redirect with session message instead of returning JSON response
                // This ensures proper UI alert is shown instead of raw JSON
                $session = \Config\Services::session();
                if ($session) {
                    $_SESSION['toastMessage'] = labels(NO_PERMISSION_TO_TAKE_THIS_ACTION, 'Sorry! You are not permitted to take this action');
                    $_SESSION['toastMessageType'] = 'error';
                    $session->markAsFlashdata('toastMessage');
                    $session->markAsFlashdata('toastMessageType');
                }
                return redirect()->to(base_url('admin/partners'));
            }
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('duplicate_provider', 'Duplicate Provider') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'duplicate_provider');
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $partner_details = (fetch_details('partner_details', ['partner_id' => $partner_id]))[0];
                $partner_timings = (fetch_details('partner_timings', ['partner_id' => $partner_id]));

                // Multi-shift schedule grouped by day, used to prefill the duplicate provider form.
                $provider_shifts_by_day = $this->providerShifts->tableExists()
                    ? $this->providerShifts->getByPartnerGroupedByDay((int) $partner_id)
                    : [];
                $this->data['data'] = fetch_details('users', ['id' => $this->userId])[0];

                $partner_image = fetch_details('users', ['id' => $partner_id])[0]['image'];
                $fileService = service('fileService');
                $partner_details['partner_image'] = $fileService->url($partner_image, 'profile');
                $partner_details['banner'] = $fileService->url($partner_details['banner'] ?? '', 'banner');

                $settings = get_settings('general_settings', true);
                if (empty($settings)) {
                    $_SESSION['toastMessage'] = labels(PLEASE_FIRST_ADD_CURRENCY_AND_BASIC_DETAILS_IN_GENERAL_SETTINGS, 'Please first add currency and basic details in general settings');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }
                $this->data['currency'] = $settings['currency'];
                $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
                $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
                $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
                $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
                $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
                $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;
                $this->data['max_serviceable_distance_type'] = $settings['max_serviceable_distance_type'] ?? 'global';
                $this->data['partner_details'] = $partner_details;
                $this->data['partner_timings'] = $partner_timings;
                $this->data['provider_shifts_by_day'] = $provider_shifts_by_day;
                $this->data['slot_settings'] = $this->slotSettingsService->find((int) $partner_id);
                $user_details = fetch_details('users', ['id' => $partner_id])[0];
                $this->data['personal_details'] = $user_details;
                $this->data['city_name'] = fetch_details('cities', [], ['id', 'name']);
                $subscription_details = fetch_details('subscriptions', ['status' => 1]);
                $this->data['subscription_details'] = $subscription_details;

                $this->seoModel->setTableContext('providers');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($partner_id, 'full');
                $this->data['partner_seo_settings'] = $seo_settings;

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Fetch translation data for prefilling form fields when duplicating partner
                // This ensures that all language fields are prefilled with existing translation data
                $this->fetchAndSetTranslationData($partner_id, $languages);

                // Pass custom-field definitions and labels to the view.
                $cfDefs = $this->loadCustomFieldDefinitions($languages);
                $this->data['documents_custom_fields'] = $cfDefs['documents'];
                $this->data['bank_details_custom_fields'] = $cfDefs['bank_details'];
                $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

                // Fetch existing custom field values so the view can show previews (PDF links, images).
                $allCfIds = array_map(static fn($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));
                $customFieldValues = $this->getPartnerCustomFieldValuesById($partner_id, $allCfIds);

                // Resolve file URLs for file-type custom fields.
                foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                    if ($cfField['field_type'] !== 'file') {
                        continue;
                    }
                    $rawVal = $customFieldValues[$cfField['id']] ?? '';
                    if ($rawVal === '') {
                        continue;
                    }
                    $customFieldValues[$cfField['id']] = $fileService->url($rawVal, 'custom_fields');
                }

                $this->data['partner_details'] = $partner_details;
                $this->data['custom_field_values'] = $customFieldValues;

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - duplicate()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    // Private Helper functions start

    /**
     * Fetch translation data for partner and organize it for form prefilling
     * 
     * @param int $partnerId Partner ID to fetch translations for
     * @param array $languages Available languages array
     */
    private function fetchAndSetTranslationData(int $partnerId, array $languages): void
    {
        try {
            // Initialize PartnerService to fetch translations
            $partnerService = new \App\Services\PartnerService();

            // Get all translations for this partner
            $translationResult = $partnerService->getPartnerWithTranslations($partnerId);

            if ($translationResult['success']) {
                $translatedData = $translationResult['translated_data'];

                // Organize translation data by language code for easy access in view
                $organizedTranslations = [];

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    // Get translation data for this language
                    $languageTranslations = $translatedData[$languageCode] ?? [];

                    // For default language, use main table data as fallback
                    if ($isDefault) {
                        $organizedTranslations[$languageCode] = [
                            'username' => $this->data['personal_details']['username'] ?? '', // Always from users table for default language
                            'company_name' => $languageTranslations['company_name'] ?? $this->data['partner_details']['company_name'] ?? '',
                            'about' => $languageTranslations['about'] ?? $this->data['partner_details']['about'] ?? '',
                            'long_description' => $languageTranslations['long_description'] ?? $this->data['partner_details']['long_description'] ?? ''
                        ];
                    } else {
                        // For non-default languages, use translation data only
                        $organizedTranslations[$languageCode] = [
                            'username' => $languageTranslations['username'] ?? '',
                            'company_name' => $languageTranslations['company_name'] ?? '',
                            'about' => $languageTranslations['about'] ?? '',
                            'long_description' => $languageTranslations['long_description'] ?? ''
                        ];
                    }
                }

                // Load SEO translations and add to organized translations
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
                $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($partnerId);

                // Always process SEO data for all languages (even if no translations exist)
                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    // Find SEO translation for this language
                    $seoTranslation = null;
                    if (!empty($seoTranslations)) {
                        foreach ($seoTranslations as $translation) {
                            if ($translation['language_code'] === $languageCode) {
                                $seoTranslation = $translation;
                                break;
                            }
                        }
                    }

                    if ($seoTranslation) {
                        // Add SEO translation data to organized translations
                        $organizedTranslations[$languageCode]['seo_title'] = $seoTranslation['seo_title'] ?? '';
                        $organizedTranslations[$languageCode]['seo_description'] = $seoTranslation['seo_description'] ?? '';
                        $organizedTranslations[$languageCode]['seo_keywords'] = $seoTranslation['seo_keywords'] ?? '';
                        $organizedTranslations[$languageCode]['seo_schema_markup'] = $seoTranslation['seo_schema_markup'] ?? '';
                    } else {
                        // If no SEO translation exists, use base table data for default language, empty for others
                        if ($isDefault) {
                            $organizedTranslations[$languageCode]['seo_title'] = $this->data['partner_seo_settings']['title'] ?? '';
                            $organizedTranslations[$languageCode]['seo_description'] = $this->data['partner_seo_settings']['description'] ?? '';
                            $organizedTranslations[$languageCode]['seo_keywords'] = $this->data['partner_seo_settings']['keywords'] ?? '';
                            $organizedTranslations[$languageCode]['seo_schema_markup'] = $this->data['partner_seo_settings']['schema_markup'] ?? '';
                        } else {
                            $organizedTranslations[$languageCode]['seo_title'] = '';
                            $organizedTranslations[$languageCode]['seo_description'] = '';
                            $organizedTranslations[$languageCode]['seo_keywords'] = '';
                            $organizedTranslations[$languageCode]['seo_schema_markup'] = '';
                        }
                    }
                }

                // Set the organized translation data for the view
                $this->data['partner_translations'] = $organizedTranslations;
            } else {
                // If translation fetching fails, set empty translations
                $this->data['partner_translations'] = [];
                log_message('error', 'Failed to fetch partner translations for duplicate: ' . implode(', ', $translationResult['errors']));
            }
        } catch (\Exception $e) {
            // If any exception occurs, set empty translations and log the error
            $this->data['partner_translations'] = [];
            log_message('error', 'Exception while fetching partner translations for duplicate: ' . $e->getMessage());
        }
    }

    /**
     * Get validation rules for partner creation/duplication
     * Handles conditional validation for image uploads based on existing images
     * 
     * @return array Validation rules
     */
    private function getValidationRules(): array
    {
        // Check if existing images are provided (for duplicate provider scenario)
        // This allows users to duplicate providers without re-uploading images
        $existingImage = $this->request->getPost('existing_image');
        $existingBannerImage = $this->request->getPost('existing_banner_image');

        $validationRules = [
            'city' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_ENTER_CITY, 'Please Enter City')],
            ],
            'address' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_ENTER_ADDRESS, 'Address is required')],
            ],
            'partner_latitude' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_CHOOSE_PROVIDER_LOCATION, 'Please choose provider location')],
            ],
            'partner_longitude' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_ENTER_VALID_LONGITUDE, 'Longitude is required')],
            ],
            'type' => [
                'rules' => 'required',
                'errors' => ['required' => labels(PLEASE_SELECT_PROVIDERS_TYPE, 'Provider type is required')],
            ],
            'number_of_members' => [
                'rules' => 'required|numeric',
                'errors' => [
                    'required' => labels(NUMBER_OF_MEMBERS_IS_REQUIRED, 'Number of members is required'),
                    'numeric' => labels(NUMBER_OF_MEMBERS_MUST_BE_A_NUMBER, 'Number of members must be a number'),
                ],
            ],
            'visiting_charges' => [
                'rules' => 'required|numeric',
                'errors' => [
                    'required' => labels(VISITING_CHARGES_IS_REQUIRED, 'Visiting charges is required'),
                    'numeric' => labels(VISITING_CHARGES_MUST_BE_A_NUMBER, 'Visiting charges must be a number'),
                ],
            ],
            'max_serviceable_distance' => [
                'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                'errors' => [
                    'numeric' => labels('max_serviceable_distance_must_be_a_number', 'Max serviceable distance must be a number'),
                    'greater_than_equal_to' => labels('max_serviceable_distance_must_be_0_or_greater', 'Max serviceable distance must be 0 or greater'),
                ],
            ],
            'advance_booking_days' => [
                'rules' => 'required|numeric|greater_than_equal_to[1]',
                'errors' => [
                    'required' => labels(ADVANCE_BOOKING_DAYS_IS_REQUIRED, 'Advance booking days is required'),
                    'numeric' => labels(ADVANCE_BOOKING_DAYS_MUST_BE_A_NUMBER, 'Advance booking days must be a number'),
                    'greater_than_equal_to' => labels(ADVANCE_BOOKING_DAYS_MUST_BE_AT_LEAST_1, 'Advance booking days must be at least 1'),
                ],
            ],
            'start_time' => [
                'rules' => 'required',
                'errors' => ['required' => labels(START_TIME_IS_REQUIRED, 'Start time is required')],
            ],
            'end_time' => [
                'rules' => 'required',
                'errors' => ['required' => labels(END_TIME_IS_REQUIRED, 'End time is required')],
            ],
            'email' => [
                'rules' => 'required|trim|valid_email',
                'errors' => ['required' => labels(EMAIL_IS_REQUIRED, 'Email is required')],
            ],
            'phone' => [
                'rules' => 'required|numeric',
                'errors' => [
                    'required' => labels(PHONE_NUMBER_IS_REQUIRED, 'Phone number is required'),
                    'numeric' => labels(PHONE_NUMBER_MUST_BE_A_NUMBER, 'Phone number must be a number'),
                ],
            ],
            'provider_slug' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PROVIDER_SLUG_IS_REQUIRED, 'Provider slug is required')],
            ],
            'password' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PASSWORD_IS_REQUIRED, 'Password is required')],
            ],
        ];

        // Only require image upload if no existing image is provided
        if (empty($existingImage)) {
            $validationRules['image'] = [
                'rules' => 'uploaded[image]',
                'errors' => ['uploaded' => labels(PROFILE_PICTURE_IS_REQUIRED, 'Profile picture is required')],
            ];
        }

        // Only require banner image upload if no existing banner image is provided
        if (empty($existingBannerImage)) {
            $validationRules['banner_image'] = [
                'rules' => 'uploaded[banner_image]',
                'errors' => ['uploaded' => labels(BANNER_IMAGE_IS_REQUIRED, 'Banner image is required')],
            ];
        }

        return $validationRules;
    }

    private function validateCoordinates(string $latitude, string $longitude): void
    {
        // Match register method: latitude -90 to 90, longitude -180 to 180, max 7 decimal places
        if (!preg_match('/^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$/', $latitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, 'Please enter valid latitude'));
        }
        if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$/', $longitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LONGITUDE, 'Please enter a valid longitude'));
        }
    }

    private function uploadFile($file, string $path, string $errorMessage, string $folder): array
    {
        // If no file is provided or file is invalid, return empty result for optional fields
        if (!$file || !$file->isValid()) {
            return [
                'url' => '',
                'disk' => '',
            ];
        }

        // Process valid file upload
        $result = upload_file($file, $path, $errorMessage, $folder);
        if ($result['error']) {
            throw new Exception($result['message']);
        }

        return [
            'url' => $result['file_name'],
            'disk' => $result['disk'],
        ];
    }

    private function upsertPartnerCustomFields(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $db = \Config\Database::connect();

        $valuesSql = [];
        $deletionIds = [];

        foreach ($valuesById as $customFieldId => $rawValue) {
            $customFieldId = (int) $customFieldId;
            if ($customFieldId <= 0) {
                continue;
            }

            if (is_array($rawValue)) {
                $rawValue = json_encode($rawValue, JSON_UNESCAPED_SLASHES);
            }

            if ($rawValue === '' || $rawValue === [] || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . (int) $partnerId . ',' . (int) $customFieldId . ',' . $db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $db->table('partner_custom_fields')
                ->where('partner_id', (int) $partnerId)
                ->whereIn('custom_field_id', array_values(array_unique($deletionIds)))
                ->delete();
        }

        if (empty($valuesSql)) {
            return;
        }

        $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
            . implode(',', $valuesSql)
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

        $db->query($sql);
    }

    /**
     * Build the documents/bank_details custom-field definitions and their
     * per-language labels.  Extracted so add_partner(), edit_partner(), and
     * duplicate() can all share the same logic without code duplication.
     *
     * @param  array $languages  Rows from the `languages` table, each having
     *                           at least: id, code, is_default, language.
     * @return array{documents: array, bank_details: array, labels_by_language: array}
     */
    private function loadCustomFieldDefinitions(array $languages): array
    {
        $documentsCustomFields = [];
        $bankDetailsCustomFields = [];
        $customFieldLabelsByLanguage = [];

        $languageCodes = [];
        $defaultLanguageCode = '';
        foreach ($languages as $langRow) {
            $code = (string) ($langRow['code'] ?? '');
            if ($code !== '') {
                $languageCodes[] = $code;
            }
            if (!empty($langRow['is_default']) && $code !== '') {
                $defaultLanguageCode = $code;
            }
        }
        if ($defaultLanguageCode === '') {
            $defaultLanguageCode = (string) get_default_language();
        }
        $languageCodes = array_values(array_unique($languageCodes));

        if ($this->db->tableExists('custom_fields')) {
            $toBool = static function ($v): bool {
                if (is_bool($v)) {
                    return $v;
                }
                if (is_int($v)) {
                    return $v === 1;
                }
                $s = strtolower(trim((string) $v));
                return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
            };

            $customFieldRows = $this->db->table('custom_fields')
                ->select(['id', 'field_label', 'field_type', 'field_group', 'file_config', 'required', 'visible', 'sort_order'])
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $customFieldIds = array_values(array_unique(array_map(
                static fn($r) => (int) ($r['id'] ?? 0),
                array_filter($customFieldRows, static fn($r) => (int) ($r['id'] ?? 0) > 0)
            )));

            $translationsByFieldId = [];
            if (!empty($customFieldIds) && $this->db->tableExists('translated_custom_fields') && !empty($languageCodes)) {
                $translationRows = $this->db->table('translated_custom_fields tcf')
                    ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                    ->join('languages l', 'l.id = tcf.language_id')
                    ->whereIn('tcf.custom_field_id', $customFieldIds)
                    ->whereIn('l.code', $languageCodes)
                    ->get()
                    ->getResultArray();

                foreach ($translationRows as $tr) {
                    $fieldId = (int) ($tr['custom_field_id'] ?? 0);
                    $langCode = (string) ($tr['language_code'] ?? '');
                    if ($fieldId <= 0 || $langCode === '') {
                        continue;
                    }
                    $translationsByFieldId[$fieldId][$langCode] = (string) ($tr['field_label'] ?? '');
                }
            }

            foreach ($customFieldRows as $field) {
                $fieldId = (int) ($field['id'] ?? 0);
                $fieldLabelBase = (string) ($field['field_label'] ?? '');
                $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                $fieldGroup = strtolower(trim((string) ($field['field_group'] ?? '')));

                if (!in_array($fieldGroup, ['documents', 'bank_details'], true)) {
                    continue;
                }

                $required = $toBool($field['required'] ?? 0);
                $visible = $toBool($field['visible'] ?? 0);
                $sortOrder = (int) ($field['sort_order'] ?? 0);

                if (!$visible) {
                    continue;
                }
                if ($fieldId <= 0) {
                    continue;
                }

                $customFieldLabelsByLanguage[$fieldId] = [];
                foreach ($languageCodes as $langCode) {
                    $label = $translationsByFieldId[$fieldId][$langCode]
                        ?? ($defaultLanguageCode !== '' ? ($translationsByFieldId[$fieldId][$defaultLanguageCode] ?? null) : null)
                        ?? $fieldLabelBase;
                    $customFieldLabelsByLanguage[$fieldId][$langCode] = $label;
                }

                $fileConfigRaw = (string) ($field['file_config'] ?? '');
                $fileConfig = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];

                $entry = [
                    'id' => $fieldId,
                    'field_label' => $fieldLabelBase,
                    'field_type' => $fieldType,
                    'field_group' => $fieldGroup,
                    'file_config' => $fileConfig,
                    'required' => $required ? 1 : 0,
                    'sort_order' => $sortOrder,
                ];

                if ($fieldGroup === 'documents') {
                    $documentsCustomFields[] = $entry;
                } else {
                    $bankDetailsCustomFields[] = $entry;
                }
            }

            usort($documentsCustomFields, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
            usort($bankDetailsCustomFields, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        }

        return [
            'documents' => $documentsCustomFields,
            'bank_details' => $bankDetailsCustomFields,
            'labels_by_language' => $customFieldLabelsByLanguage,
        ];
    }

    private function getPartnerCustomFieldValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $db = \Config\Database::connect();

        $valueRows = $db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', (int) $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($valueRows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }

    private function saveUser(array $userData): int
    {
        // Hash the password before inserting
        $ion_auth = new IonAuthModel();
        $userData['password'] = $ion_auth->hashPassword($userData['password']);

        // Insert user into database using Users model
        $insert_id = $this->users->insert($userData);

        // Verify insertion was successful
        if (!$insert_id) {
            log_message('error', "Failed to insert user: " . json_encode($userData) . " Model Error: " . json_encode($this->users->errors()));
            throw new Exception(labels(USER_CREATION_FAILED_PLEASE_TRY_AGAIN, 'User creation failed! Please try again'));
        }

        return $insert_id;
    }

    private function savePartner(int $partnerId, array $partnerData): void
    {
        if (!$this->partner->insert($partnerData)) {
            throw new Exception(labels(PARTNER_CREATION_FAILED_PLEASE_TRY_AGAIN, 'Partner creation failed! Please try again'));
        }
    }

    private function savePartnerTimings(int $partnerId, array $startTimes, array $endTimes, array $postData): void
    {
        $extraShifts = $postData['extra_shifts'] ?? [];
        $rows = [];

        foreach (ProviderShifts_model::DAYS as $i => $day) {
            $isOpen = isset($postData[$day]) ? 1 : 0;
            $shiftNumber = 1;

            $rows[] = [
                'day' => $day,
                'shift_number' => $shiftNumber,
                'opening_time' => $startTimes[$i] ?? '',
                'closing_time' => $endTimes[$i] ?? '',
                'is_open' => $isOpen,
            ];

            $dayExtras = $extraShifts[$day] ?? [];
            $extraStarts = $dayExtras['start'] ?? [];
            $extraEnds = $dayExtras['end'] ?? [];
            $count = min(count($extraStarts), count($extraEnds));

            for ($j = 0; $j < $count; $j++) {
                $start = trim((string) $extraStarts[$j]);
                $end = trim((string) $extraEnds[$j]);
                if ($start === '' || $end === '') {
                    continue;
                }
                $shiftNumber++;
                $rows[] = [
                    'day' => $day,
                    'shift_number' => $shiftNumber,
                    'opening_time' => $start,
                    'closing_time' => $end,
                    'is_open' => $isOpen,
                ];
            }
        }

        $this->providerShifts->insertBatchForPartner($partnerId, $rows);
    }

    private function assignUserGroup(int $partnerId): void
    {
        if (!exists(['user_id' => $partnerId, 'group_id' => 3], 'users_groups')) {
            insert_details(['user_id' => $partnerId, 'group_id' => 3], 'users_groups');
        }
    }

    /**
     * Transform form data to translated_fields structure
     * 
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code
     * @return array Translated fields structure
     */
    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage): array
    {
        $translatedFields = [
            'username' => [],
            'company_name' => [],
            'about_provider' => [],
            'long_description' => [],
            'seo_title' => [],
            'seo_description' => [],
            'seo_keywords' => [],
            'seo_schema_markup' => []
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['company_name']) && is_array($postData['company_name'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['username'] = $postData['username'] ?? [];
            $translatedFields['company_name'] = $postData['company_name'] ?? [];
            $translatedFields['about_provider'] = $postData['about_provider'] ?? [];
            $translatedFields['long_description'] = $postData['long_description'] ?? [];
            $translatedFields['seo_title'] = $postData['meta_title'] ?? [];
            $translatedFields['seo_description'] = $postData['meta_description'] ?? [];

            // Process keywords data properly - handle array structure with JSON strings
            $metaKeywords = $postData['meta_keywords'] ?? [];
            $processedKeywords = [];
            foreach ($metaKeywords as $langCode => $keywordsData) {
                if (is_array($keywordsData)) {
                    // Handle array format (like from Tagify)
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        // Single JSON string in array format - keep as is for parseKeywords to handle
                        $processedKeywords[$langCode] = $keywordsData[0];
                    } else {
                        // Multiple values, join them
                        $processedKeywords[$langCode] = implode(',', $keywordsData);
                    }
                } else {
                    // Direct string value
                    $processedKeywords[$langCode] = $keywordsData;
                }
            }
            $translatedFields['seo_keywords'] = $processedKeywords;

            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process username
            $usernameField = 'username[' . $languageCode . ']';
            $usernameValue = $postData[$usernameField] ?? null;
            if (!empty($usernameValue)) {
                $translatedFields['username'][$languageCode] = trim($usernameValue);
            }

            // Process company_name
            $companyNameField = 'company_name[' . $languageCode . ']';
            $companyNameValue = $postData[$companyNameField] ?? null;
            if (!empty($companyNameValue)) {
                $translatedFields['company_name'][$languageCode] = trim($companyNameValue);
            }

            // Process about_provider
            $aboutField = 'about_provider[' . $languageCode . ']';
            $aboutValue = $postData[$aboutField] ?? null;
            if (!empty($aboutValue)) {
                $translatedFields['about_provider'][$languageCode] = trim($aboutValue);
            }

            // Process long_description
            $descriptionField = 'long_description[' . $languageCode . ']';
            $descriptionValue = $postData[$descriptionField] ?? null;
            if (!empty($descriptionValue)) {
                $translatedFields['long_description'][$languageCode] = trim($descriptionValue);
            }

            // Process SEO fields (meta_ prefixed from form)
            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                // Record even empty strings so cleared values overwrite previous data
                $seoTitleValue = $postData[$seoTitleField];
                $translatedFields['seo_title'][$languageCode] = trim((string) $seoTitleValue);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                // Preserve intent when user submits blank description during edits
                $seoDescriptionValue = $postData[$seoDescriptionField];
                $translatedFields['seo_description'][$languageCode] = trim((string) $seoDescriptionValue);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                if (is_array($seoKeywordsValue)) {
                    // Handle array format from Tagify while allowing empty arrays to clear data
                    $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                } else {
                    $translatedFields['seo_keywords'][$languageCode] = trim((string) $seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                // Ensure blank schema submissions replace previous schema content
                $seoSchemaValue = $postData[$seoSchemaField];
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string) $seoSchemaValue);
            }
        }

        return $translatedFields;
    }

    private function saveSeoSettings(int $partnerId): void
    {
        // Get default language for SEO data
        $defaultLanguage = get_default_language();

        // Get all POST data and transform it to translated fields structure
        $postData = $this->request->getPost();

        // Transform form data to translated fields structure
        $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

        // Extract default language SEO data
        $defaultSeoTitle = '';
        $defaultSeoDescription = '';
        $defaultSeoKeywords = '';
        $defaultSeoSchema = '';

        // Log extraction process for each field
        if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
            $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
            $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
            $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];

            // Handle different data structures for keywords
            if (is_array($keywordsData)) {
                // If it's an array, it might contain JSON strings or direct values
                if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                    // Single JSON string in array format
                    $defaultSeoKeywords = $keywordsData[0];
                } else {
                    // Multiple values, join them
                    $defaultSeoKeywords = implode(',', $keywordsData);
                }
            } else {
                // Direct string value
                $defaultSeoKeywords = $keywordsData;
            }
        }

        if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
            $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
        }

        // Parse meta keywords (Tagify or comma-separated)
        $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

        // Build SEO data array
        $seoData = [
            'title' => $defaultSeoTitle,
            'description' => $defaultSeoDescription,
            'keywords' => $keywords,
            'schema_markup' => $defaultSeoSchema,
            'partner_id' => $partnerId,
        ];

        // Check if any SEO field is filled (excluding partner_id)
        $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $partnerId);

        // Check if all SEO fields are intentionally cleared
        $allFieldsCleared = empty($seoData['title']) &&
            empty($seoData['description']) &&
            empty($seoData['keywords']) &&
            empty($seoData['schema_markup']);

        // Handle SEO image upload
        $seoImage = $this->request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        // Use Seo_model for provider context
        $this->seoModel->setTableContext('providers');
        $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

        $newSeoData = $seoData;
        if ($hasImage) {
            try {
                $uploadResult = $this->uploadFile(
                    $seoImage,
                    'public/uploads/seo_settings/provider_seo_settings/',
                    labels(FAILED_TO_UPLOAD_SEO_IMAGE, 'Failed to upload SEO image'),
                    'seo_settings'
                );
                $newSeoData['image'] = $uploadResult['url'];
            } catch (\Throwable $t) {
                throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed: ' . $t->getMessage()));
            }
        } else {
            $newSeoData['image'] = $existingSettings['image'] ?? '';
        }

        // If no existing settings, create new if data or image exists
        if (!$existingSettings) {
            if ($hasSeoData || $hasImage) {
                $result = $this->seoModel->createSeoSettings($newSeoData);
                if (!empty($result['error'])) {
                    $errors = $result['validation_errors'] ?? [];
                    throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                }
            }

            // Process SEO translations after creating base SEO settings
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // If existing settings exist and all fields are cleared (and no new image), delete the record
        // BUT: If there's an existing image, we should NOT delete the record even if all other fields are empty
        // This preserves the SEO record structure for future use
        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            $result = $this->seoModel->delete($existingSettings['id']);
            if ($result) {
                // Clean up old image if it exists
                if (!empty($existingSettings['image'])) {
                    $disk = fetch_current_file_manager();
                    delete_file_based_on_server('provider_seo_settings', $existingSettings['image'], $disk);
                }
            }
            // Also clean up SEO translations when deleting base SEO settings
            $this->cleanupSeoTranslations($partnerId);
            return;
        }

        // Force clearing removed SEO fields
        $emptyDefaults = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'schema_markup' => '',
            'image' => $existingSettings['image'] ?? '' // keep old image only if not changed
        ];

        foreach ($emptyDefaults as $key => $defaultVal) {
            if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                $newSeoData[$key] = $defaultVal;
            }
        }

        // Compare existing and new settings
        $settingsChanged = false;
        foreach ($newSeoData as $key => $value) {
            $existingValue = $existingSettings[$key] ?? '';
            $newValue = $value ?? '';
            if ($existingValue !== $newValue) {
                $settingsChanged = true;
                break;
            }
        }
        if (!$settingsChanged) {
            // Even if base SEO settings haven't changed, process translations
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // Update existing settings with new data
        $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
        if (!empty($result['error'])) {
            $errors = $result['validation_errors'] ?? [];
            throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
        }

        // Process SEO translations after updating base SEO settings
        $this->processSeoTranslations($partnerId, $translatedFields);
    }

    /**
     * Parses meta keywords input from Tagify.
     */
    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON or comma-separated
        if (is_string($input)) {
            // Check if it's a JSON string
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            // Handle case where array contains a single JSON string (e.g., ['[{value: "tag1"}, {value: "tag2"}]'])
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        // Fallback: return empty string for unexpected input
        return '';
    }

    /**
     * Process SEO translations for partner if provided in the request
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function processSeoTranslations(int $partnerId, ?array $translatedFields = null): void
    {
        try {

            // Use provided translated fields or get from POST request (fallback)
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');

                // If translated fields are provided as JSON string, decode it
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {

                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($partnerId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function cleanupSeoTranslations(int $partnerId): void
    {
        try {

            // Load the SEO translation model
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

            // Delete all SEO translations for this partner
            $result = $seoTranslationModel->deletePartnerSeoTranslations($partnerId);
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new Exception('Exception in cleanupSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
     * 
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];

        // SEO fields we want to process
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Get all available languages from the translated fields
        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $languages = array_merge($languages, array_keys($translatedFields[$field]));
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                $value = $translatedFields[$field][$languageCode] ?? '';

                if ($field === 'seo_keywords') {
                    $restructured[$languageCode][$field] = !empty($value)
                        ? $this->parseKeywords($value)
                        : '';
                } else {
                    $restructured[$languageCode][$field] = $value !== null
                        ? $value
                        : '';
                }
            }
            // Keep the language entry even if every field is empty.
            // This lets the translation model overwrite stale values with blanks.
        }


        return $restructured;
    }

    /**
     * Fetch all visible file-type custom fields (documents + bank_details groups).
     *
     * Each row includes: id, field_type, field_group, required (bool),
     * file_config (decoded array).
     */
    private function getVisibleFileCustomFields(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $rows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group', 'required', 'file_config'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->where('field_type', 'file')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $fileConfigRaw = (string) ($row['file_config'] ?? '');
            $row['file_config'] = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];
            $row['required'] = (int) ($row['required'] ?? 0) === 1;
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Convert file extensions (e.g. ['.jpg', '.pdf']) to MIME types for CI4 validation.
     */
    private function extensionsToMimeTypes(array $extensions): array
    {
        $map = [
            '.jpg' => 'image/jpeg',
            '.jpeg' => 'image/jpeg',
            '.png' => 'image/png',
            '.gif' => 'image/gif',
            '.webp' => 'image/webp',
            '.bmp' => 'image/bmp',
            '.tif' => 'image/tiff',
            '.tiff' => 'image/tiff',
            '.svg' => 'image/svg+xml',
            '.pdf' => 'application/pdf',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower(trim((string) $ext));
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return array_values(array_unique($mimes));
    }

    private function collectCustomFieldValuesFromPost(array $uploadedFileValues = []): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $customFieldRows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->get()
            ->getResultArray();

        $valuesById = [];
        foreach ($customFieldRows as $fieldRow) {
            $cfId = (int) ($fieldRow['id'] ?? 0);
            $fieldType = strtolower(trim((string) ($fieldRow['field_type'] ?? 'text')));

            if ($cfId <= 0) {
                continue;
            }

            $inputName = 'cf_' . $cfId;

            if ($fieldType === 'file') {
                if (array_key_exists($cfId, $uploadedFileValues)) {
                    $valuesById[$cfId] = $uploadedFileValues[$cfId];
                }
                continue;
            }

            $valuesById[$cfId] = $this->request->getPost($inputName) ?? '';
        }

        return $valuesById;
    }
}
