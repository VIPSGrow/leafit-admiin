<?php

namespace App\Controllers\Admin;

use App\Models\Settings;
use App\Models\Language_model;

class AppSettingsController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;
    private Language_model $languageModel;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin    = $this->session->get('email');
        $this->settingsModel = new Settings();
        $this->languageModel = new Language_model();
        helper('ResponceServices');
        helper('function');
    }

    public function startQueueWorker()
    {
        $output = null;
        $retval = null;

        exec('/opt/lampp/bin/php /opt/lampp/htdocs/edemand/index.php queue:work 2>&1', $output, $retval);

        log_message('error', 'Queue Worker Output: ' . implode("\n", $output));
        log_message('error', 'Queue Worker Return Code: ' . $retval);
    }

    public function app_settings()
    {
        try {
            if ($this->request->getPost('update')) {

                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                }

                $existing = get_settings('app_settings', true) ?: [];

                // --- App store URLs ---
                $updated['customer_playstore_url'] = $this->request->getPost('customer_playstore_url') ?? ($existing['customer_playstore_url'] ?? '');
                $updated['customer_appstore_url']  = $this->request->getPost('customer_appstore_url')  ?? ($existing['customer_appstore_url'] ?? '');
                $updated['provider_playstore_url'] = $this->request->getPost('provider_playstore_url') ?? ($existing['provider_playstore_url'] ?? '');
                $updated['provider_appstore_url']  = $this->request->getPost('provider_appstore_url')  ?? ($existing['provider_appstore_url'] ?? '');

                // --- Version settings ---
                $updated['customer_current_version_android_app'] = $this->request->getPost('customer_current_version_android_app') ?? ($existing['customer_current_version_android_app'] ?? '');
                $updated['customer_current_version_ios_app']     = $this->request->getPost('customer_current_version_ios_app')     ?? ($existing['customer_current_version_ios_app'] ?? '');
                $updated['provider_current_version_android_app'] = $this->request->getPost('provider_current_version_android_app') ?? ($existing['provider_current_version_android_app'] ?? '');
                $updated['provider_current_version_ios_app']     = $this->request->getPost('provider_current_version_ios_app')     ?? ($existing['provider_current_version_ios_app'] ?? '');

                // --- AdMob IDs ---
                $updated['customer_android_google_interstitial_id'] = $this->request->getPost('customer_android_google_interstitial_id') ?? ($existing['customer_android_google_interstitial_id'] ?? '');
                $updated['customer_android_google_banner_id']        = $this->request->getPost('customer_android_google_banner_id')        ?? ($existing['customer_android_google_banner_id'] ?? '');
                $updated['provider_android_google_interstitial_id'] = $this->request->getPost('provider_android_google_interstitial_id') ?? ($existing['provider_android_google_interstitial_id'] ?? '');
                $updated['provider_android_google_banner_id']        = $this->request->getPost('provider_android_google_banner_id')        ?? ($existing['provider_android_google_banner_id'] ?? '');
                $updated['customer_ios_google_interstitial_id']     = $this->request->getPost('customer_ios_google_interstitial_id')     ?? ($existing['customer_ios_google_interstitial_id'] ?? '');
                $updated['customer_ios_google_banner_id']            = $this->request->getPost('customer_ios_google_banner_id')            ?? ($existing['customer_ios_google_banner_id'] ?? '');
                $updated['provider_ios_google_interstitial_id']     = $this->request->getPost('provider_ios_google_interstitial_id')     ?? ($existing['provider_ios_google_interstitial_id'] ?? '');
                $updated['provider_ios_google_banner_id']            = $this->request->getPost('provider_ios_google_banner_id')            ?? ($existing['provider_ios_google_banner_id'] ?? '');

                // --- Boolean toggles (checkboxes — default to '0' when absent from POST) ---
                foreach ([
                    'customer_compulsary_update_force_update',
                    'provider_compulsary_update_force_update',
                    'customer_android_google_ads_status',
                    'provider_android_google_ads_status',
                    'customer_ios_google_ads_status',
                    'provider_ios_google_ads_status',
                    'customer_app_maintenance_mode',
                    'provider_app_maintenance_mode',
                ] as $key) {
                    $post = $this->request->getPost($key);
                    $updated[$key] = ($post !== null) ? (string)(int)$post : '0';
                }

                // --- AdMob validation: IDs required when ads status is on ---
                $admobValidationMap = [
                    'customer_android_google_ads_status' => [
                        'customer_android_google_interstitial_id' => 'google_interstitial_id_is_required_for_android_customer_application',
                        'customer_android_google_banner_id'       => 'google_banner_id_is_required_for_android_customer_application',
                    ],
                    'provider_android_google_ads_status' => [
                        'provider_android_google_interstitial_id' => 'google_interstitial_id_is_required_for_android_provider_application',
                        'provider_android_google_banner_id'       => 'google_banner_id_is_required_for_android_provider_application',
                    ],
                    'customer_ios_google_ads_status' => [
                        'customer_ios_google_interstitial_id' => 'google_interstitial_id_is_required_for_ios_customer_application',
                        'customer_ios_google_banner_id'       => 'google_banner_id_is_required_for_ios_customer_application',
                    ],
                    'provider_ios_google_ads_status' => [
                        'provider_ios_google_interstitial_id' => 'google_interstitial_id_is_required_for_ios_provider_application',
                        'provider_ios_google_banner_id'       => 'google_banner_id_is_required_for_ios_provider_application',
                    ],
                ];
                $admobErrors = [];
                foreach ($admobValidationMap as $statusKey => $idFields) {
                    if ($updated[$statusKey] === '1') {
                        foreach ($idFields as $idKey => $labelKey) {
                            if (empty(trim($updated[$idKey] ?? ''))) {
                                $admobErrors[] = labels($labelKey, $labelKey);
                            }
                        }
                    }
                }
                if (!empty($admobErrors)) {
                    return JsonError(implode('<br>', $admobErrors));
                }

                // --- Maintenance schedule dates (store UTC) ---
                $maintenanceInputTz = $this->request->getPost('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                if ($this->request->getPost('maintenance_schedule_entry_timezone')) {
                    $this->session->set('maintenance_schedule_entry_timezone', $maintenanceInputTz);
                }

                $customerSchedule = $this->request->getPost('customer_app_maintenance_schedule_date');
                if ($customerSchedule !== null) {
                    $updated['customer_app_maintenance_schedule_date'] = !empty(trim($customerSchedule))
                        ? maintenance_schedule_local_to_utc($customerSchedule, $maintenanceInputTz)
                        : '';
                } else {
                    $updated['customer_app_maintenance_schedule_date'] = $existing['customer_app_maintenance_schedule_date'] ?? '';
                }

                $providerSchedule = $this->request->getPost('provider_app_maintenance_schedule_date');
                if ($providerSchedule !== null) {
                    $updated['provider_app_maintenance_schedule_date'] = !empty(trim($providerSchedule))
                        ? maintenance_schedule_local_to_utc($providerSchedule, $maintenanceInputTz)
                        : '';
                } else {
                    $updated['provider_app_maintenance_schedule_date'] = $existing['provider_app_maintenance_schedule_date'] ?? '';
                }

                // --- Multi-language maintenance messages ---
                $updated['message_for_customer_application'] = $_POST['message_for_customer_application'] ?? ($existing['message_for_customer_application'] ?? []);
                $updated['message_for_provider_application'] = $_POST['message_for_provider_application'] ?? ($existing['message_for_provider_application'] ?? []);

                // --- Merge with existing app_settings to preserve any keys not in this form ---
                $finalData = array_merge($existing, $updated);

                $oldProviderMode = (string) ($existing['provider_app_maintenance_mode'] ?? '0');
                $oldCustomerMode = (string) ($existing['customer_app_maintenance_mode'] ?? '0');

                if (!$this->settingsModel->upsert('app_settings', json_encode($finalData))) {
                    return JsonError(labels('Unable to update the App settings', 'Unable to update the App settings'));
                }

                // provider_location_in_provider_details stays in general_settings — save it separately
                $generalData = get_settings('general_settings', true);
                $post = $this->request->getPost('provider_location_in_provider_details');
                $generalData['provider_location_in_provider_details'] = ($post !== null) ? (string)(int)$post : '0';
                $this->settingsModel->upsert('general_settings', json_encode($generalData));

                // Maintenance mode notifications — fire only on 0→1 transitions
                if ($oldProviderMode !== '1' && $updated['provider_app_maintenance_mode'] === '1') {
                    try {
                        queue_notification_service(
                            eventType: 'maintenance_mode',
                            recipients: [],
                            context: [],
                            options: ['channels' => ['fcm', 'email', 'sms'], 'user_groups' => [3], 'platforms' => ['android', 'ios', 'provider_panel']]
                        );
                    } catch (\Throwable $e) {
                        log_message('error', '[MAINTENANCE_MODE] provider notification error: ' . $e->getTraceAsString());
                    }
                }
                if ($oldCustomerMode !== '1' && $updated['customer_app_maintenance_mode'] === '1') {
                    try {
                        queue_notification_service(
                            eventType: 'maintenance_mode',
                            recipients: [],
                            context: [],
                            options: ['channels' => ['fcm', 'email', 'sms'], 'user_groups' => [2], 'platforms' => ['android', 'ios', 'web']]
                        );
                    } catch (\Throwable $e) {
                        log_message('error', '[MAINTENANCE_MODE] customer notification error: ' . $e->getTraceAsString());
                    }
                }

                return JsonSuccess(labels('App settings has been successfully updated', 'App settings has been successfully updated'));
            }

            // GET — load settings into view
            $settings = get_settings('app_settings', true);
            if (!empty($settings)) {
                    // Multi-language maintenance messages backward-compat
                    $displayTz = $this->session->get('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();

                    foreach (['message_for_customer_application', 'message_for_provider_application'] as $field) {
                        if (!isset($settings[$field])) {
                            $settings[$field] = [];
                        } elseif (is_string($settings[$field])) {
                            $defaultLang = 'en';
                            $langs = $this->languageModel->where('is_default', 1)->select('code')->findAll();
                            if (!empty($langs)) {
                                $defaultLang = $langs[0]['code'];
                            }
                            $settings[$field] = [$defaultLang => $settings[$field]];
                        }
                    }

                    if (!empty($settings['customer_app_maintenance_schedule_date'])) {
                        $settings['customer_app_maintenance_schedule_date'] = maintenance_schedule_utc_to_local($settings['customer_app_maintenance_schedule_date'], $displayTz);
                    }
                    if (!empty($settings['provider_app_maintenance_schedule_date'])) {
                        $settings['provider_app_maintenance_schedule_date'] = maintenance_schedule_utc_to_local($settings['provider_app_maintenance_schedule_date'], $displayTz);
                    }

                $this->data = array_merge($this->data, $settings);
            }

            // provider_location_in_provider_details lives in general_settings
            $generalSettings = get_settings('general_settings', true);
            $this->data['provider_location_in_provider_details'] = $generalSettings['provider_location_in_provider_details'] ?? '0';

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            usort($languages, fn($a, $b) => $b['is_default'] <=> $a['is_default']);
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                foreach (['message_for_customer_application', 'message_for_provider_application'] as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        $this->data[$field] = $this->ensureMultiLangFallbacks($this->data[$field], $languages);
                    }
                }
            }

            setPageInfo($this->data, labels('App Settings', 'App Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'app');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> AppSettingsController::app_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    private function ensureMultiLangFallbacks(array $translations, array $allLanguages): array
    {
        if (empty($translations)) {
            return [];
        }

        $defaultLang = 'en';
        foreach ($allLanguages as $lang) {
            if ($lang['is_default'] == 1) {
                $defaultLang = $lang['code'];
                break;
            }
        }

        $fallbackValue = resolve_translation_fallback($translations, ['en']);

        if (empty($translations[$defaultLang]) && !empty($fallbackValue)) {
            $translations[$defaultLang] = $fallbackValue;
        }

        foreach ($allLanguages as $lang) {
            if (empty($translations[$lang['code']]) && !empty($fallbackValue)) {
                $translations[$lang['code']] = $fallbackValue;
            }
        }

        return $translations;
    }
}
