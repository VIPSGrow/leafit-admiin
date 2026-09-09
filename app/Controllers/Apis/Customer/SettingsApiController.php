<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\Language_model;
use App\Models\Faqs_model;
use App\Models\TranslatedServiceDetails_model;

class SettingsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
    protected $builder;
    protected $user_details = [];
    protected $excluded_routes =
        [
            "api/v1/index",
            "api/v1",
            "api/v1/get_settings",
            "api/v1/get_web_landing_page_settings",
            "api/v1/get_become_provider_settings"
        ];

    private $allowed_settings = ["general_settings", "terms_conditions", "privacy_policy", "about_us", 'payment_gateways_settings', 'chat_settings'];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_settings()
    {
        try {
            $variable = (isset($_POST['variable']) && !empty($_POST['variable'])) ? $_POST['variable'] : 'all';
            $setting = array();
            $setting = fetch_details('settings', '', 'variable', '', '', '', 'ASC');

            if (isset($variable) && !empty($variable) && in_array(trim($variable), $this->allowed_settings)) {
                $setting_res[$variable] = get_settings($variable, true);
            } else {
                foreach ($setting as $type) {
                    $notallowed_settings = ["languages", "email_settings", "country_codes", "api_key_settings", "test"];
                    if (!in_array($type['variable'], $notallowed_settings)) {
                        $setting_res[$type['variable']] = get_settings($type['variable'], true);
                    }
                }
            }

            $setting_res['map_provider'] = get_map_provider() === 'openstreetmap' ? 'openstreetmap' : 'google';

            $general_settings = $setting_res['general_settings'];
            $general_settings['passport_verification_status'] = $general_settings['passport_verification_status'] ? $general_settings['passport_verification_status'] : "0";
            $general_settings['national_id_verification_status'] = $general_settings['national_id_verification_status'] ? $general_settings['national_id_verification_status'] : "0";
            $general_settings['address_id_verification_status'] = $general_settings['address_id_verification_status'] ? $general_settings['address_id_verification_status'] : "0";
            $general_settings['passport_required_status'] = $general_settings['passport_required_status'] ? $general_settings['passport_required_status'] : "0";
            $general_settings['national_id_required_status'] = $general_settings['national_id_required_status'] ? $general_settings['national_id_required_status'] : "0";
            $general_settings['address_id_required_status'] = $general_settings['address_id_required_status'] ? $general_settings['address_id_required_status'] : "0";

            $general_settings['default_country_code'] = fetch_details('country_codes', ['is_default' => 1], ['country_code'])[0]['country_code'] ?? 'IN';

            $setting_res['general_settings'] = $general_settings;


            // Only include payment gateway settings if user is authenticated (has valid token)
            if (!empty($this->user_details) && isset($this->user_details['id'])) {
                // User is logged in - include payment gateway settings
                $payment_gateway_settings = $setting_res['payment_gateways_settings'];
                $unset_keys = [
                    'xendit_currency',
                    'xendit_api_key',
                    'xendit_endpoint',
                    'xendit_webhook_verification_token',
                    'cashfree_currency',
                    'cashfree_secret_key',
                    'cashfree_webhook_secret_key',
                    'cashfree_endpoint',
                    'cashfree_website_url'
                ];
                foreach ($unset_keys as $key) {
                    if (array_key_exists($key, $payment_gateway_settings)) {
                        unset($payment_gateway_settings[$key]);
                    }
                }
                $setting_res['payment_gateways_settings'] = $payment_gateway_settings;
            } else {
                // User is not logged in - remove payment gateway settings from response
                if (isset($setting_res['payment_gateways_settings'])) {
                    unset($setting_res['payment_gateways_settings']);
                }
            }

            $this->toDateTime = date('Y-m-d H:i');
            $this->db = \Config\Database::connect();
            $this->builder = $this->db->table('settings');

            // Maintenance schedule is stored in UTC in the database. All comparisons are done in UTC.
            //
            // UTC guarantee: The following fields in the get_settings response are ALWAYS in UTC:
            // - customer_web_maintenance_mode_start_datetime (web_settings)
            // - customer_web_maintenance_mode_end_datetime (web_settings)
            // - customer_web_maintenance_schedule_date (web_settings; raw "start to end" string from DB, stored in UTC)
            // - customer_app_maintenance_mode_start_datetime (app_settings)
            // - customer_app_maintenance_mode_end_datetime (app_settings)
            // - customer_app_maintenance_schedule_date (general_settings; raw "start to end" string from DB, stored in UTC)
            // The raw schedule_date strings are stored in UTC in the database and are returned as-is. The _start_datetime and _end_datetime are derived from them and formatted in UTC (Y-m-d H:i:s).
            $system_time_zone = isset($setting_res['general_settings']['system_timezone']) ? $setting_res['general_settings']['system_timezone'] : "Asia/Kolkata";
            date_default_timezone_set($system_time_zone);

            // Parse customer app maintenance schedule date range (stored as "start to end" in UTC).
            $customer_app_maintenance_mode_schedule_date = isset($setting_res['app_settings']['customer_app_maintenance_schedule_date']) ? (explode("to", $setting_res['app_settings']['customer_app_maintenance_schedule_date'])) : null;
            if (!empty($customer_app_maintenance_mode_schedule_date)) {
                $customer_app_maintenance_mode_start_date = isset($customer_app_maintenance_mode_schedule_date[0]) ? trim($customer_app_maintenance_mode_schedule_date[0]) : "";
                $customer_app_maintenance_mode_end_date = isset($customer_app_maintenance_mode_schedule_date[1]) ? trim($customer_app_maintenance_mode_schedule_date[1]) : "";
            } else {
                $customer_app_maintenance_mode_start_date = null;
                $customer_app_maintenance_mode_end_date = null;
            }

            // Ensure app_settings exists before we start assigning new keys into it.
            if (!isset($setting_res['app_settings']) || !is_array($setting_res['app_settings'])) {
                $setting_res['app_settings'] = [];
            }

            // Initialize new maintenance mode fields with safe defaults.
            $setting_res['app_settings']['customer_app_scheduled_maintenance_mode'] = "0";
            $setting_res['app_settings']['customer_app_maintenance_mode_start_datetime'] = null;
            $setting_res['app_settings']['customer_app_maintenance_mode_end_datetime'] = null;

            if (!empty($customer_app_maintenance_mode_start_date) && !empty($customer_app_maintenance_mode_end_date)) {
                try {
                    // Stored schedule is already in UTC; parse as UTC for all comparisons and API output.
                    $utcTimeZoneObj = new \DateTimeZone('UTC');
                    $startDateTimeUtc = new \DateTime($customer_app_maintenance_mode_start_date, $utcTimeZoneObj);
                    $endDateTimeUtc = new \DateTime($customer_app_maintenance_mode_end_date, $utcTimeZoneObj);

                    // Expose UTC datetimes to the API (always UTC in get_settings response).
                    $setting_res['app_settings']['customer_app_maintenance_mode_start_datetime'] = $startDateTimeUtc->format('Y-m-d H:i:s');
                    $setting_res['app_settings']['customer_app_maintenance_mode_end_datetime'] = $endDateTimeUtc->format('Y-m-d H:i:s');

                    // All comparisons in UTC: is maintenance scheduled in the future?
                    $nowUtc = new \DateTime('now', $utcTimeZoneObj);
                    if (
                        isset($setting_res['app_settings']['customer_app_maintenance_mode']) &&
                        $setting_res['app_settings']['customer_app_maintenance_mode'] == 1 &&
                        $startDateTimeUtc > $nowUtc
                    ) {
                        $setting_res['app_settings']['customer_app_scheduled_maintenance_mode'] = "1";
                    }
                } catch (\Exception $e) {
                    // In case of any parsing / timezone error, we keep the default values.
                }
            }

            // Decide if the app is currently in maintenance mode. All comparisons in UTC.
            if (isset($setting_res['app_settings']['customer_app_maintenance_mode']) && $setting_res['app_settings']['customer_app_maintenance_mode'] == 1) {
                try {
                    $utcTimeZoneObj = new \DateTimeZone('UTC');
                    $nowUtc = new \DateTime('now', $utcTimeZoneObj);
                    $startUtc = new \DateTime($customer_app_maintenance_mode_start_date, $utcTimeZoneObj);
                    $endUtc = new \DateTime($customer_app_maintenance_mode_end_date, $utcTimeZoneObj);
                    if (($nowUtc >= $startUtc) && ($nowUtc <= $endUtc)) {
                        $setting_res['app_settings']['customer_app_maintenance_mode'] = "1";
                    } else {
                        $setting_res['app_settings']['customer_app_maintenance_mode'] = "0";
                    }
                } catch (\Exception $e) {
                    $setting_res['app_settings']['customer_app_maintenance_mode'] = "0";
                }
            } else {
                $setting_res['app_settings']['customer_app_maintenance_mode'] = "0";
            }

            // Parse customer web maintenance schedule date range (stored as a single "start to end" string).
            // This follows the same pattern as customer app maintenance mode, but for web_settings.
            $customer_web_maintenance_mode_schedule_date = isset($setting_res['web_settings']['customer_web_maintenance_schedule_date'])
                ? explode("to", $setting_res['web_settings']['customer_web_maintenance_schedule_date'])
                : null;
            if (!empty($customer_web_maintenance_mode_schedule_date)) {
                $customer_web_maintenance_mode_start_date = isset($customer_web_maintenance_mode_schedule_date[0]) ? trim($customer_web_maintenance_mode_schedule_date[0]) : "";
                $customer_web_maintenance_mode_end_date = isset($customer_web_maintenance_mode_schedule_date[1]) ? trim($customer_web_maintenance_mode_schedule_date[1]) : "";
            } else {
                $customer_web_maintenance_mode_start_date = null;
                $customer_web_maintenance_mode_end_date = null;
            }

            // Ensure web_settings exists before we start assigning new keys into it.
            if (!isset($setting_res['web_settings']) || !is_array($setting_res['web_settings'])) {
                $setting_res['web_settings'] = [];
            }

            // Initialize new web maintenance mode fields with safe defaults.
            // These fields are added for web clients to clearly understand:
            // 1. If maintenance is scheduled in the future (customer_web_scheduled_maintenance_mode).
            // 2. Exact UTC start and end datetimes for that maintenance window.
            $setting_res['web_settings']['customer_web_scheduled_maintenance_mode'] = "0";
            $setting_res['web_settings']['customer_web_maintenance_mode_start_datetime'] = null;
            $setting_res['web_settings']['customer_web_maintenance_mode_end_datetime'] = null;

            if (!empty($customer_web_maintenance_mode_start_date) && !empty($customer_web_maintenance_mode_end_date)) {
                try {
                    // Stored schedule is already in UTC (same as customer_app_maintenance_schedule_date).
                    // Parse as UTC so API returns correct UTC start/end datetimes.
                    $utcTimeZoneObj = new \DateTimeZone('UTC');
                    $webStartDateTimeUtc = new \DateTime($customer_web_maintenance_mode_start_date, $utcTimeZoneObj);
                    $webEndDateTimeUtc = new \DateTime($customer_web_maintenance_mode_end_date, $utcTimeZoneObj);

                    // Expose UTC datetimes to the API (always UTC in get_settings response).
                    $setting_res['web_settings']['customer_web_maintenance_mode_start_datetime'] = $webStartDateTimeUtc->format('Y-m-d H:i:s');
                    $setting_res['web_settings']['customer_web_maintenance_mode_end_datetime'] = $webEndDateTimeUtc->format('Y-m-d H:i:s');

                    // Determine if web maintenance is scheduled in the future. Use UTC for consistency.
                    $nowUtc = new \DateTime('now', $utcTimeZoneObj);
                    if (
                        isset($setting_res['web_settings']['customer_web_maintenance_mode']) &&
                        $setting_res['web_settings']['customer_web_maintenance_mode'] == 1 &&
                        $webStartDateTimeUtc > $nowUtc
                    ) {
                        // Maintenance mode is enabled and the start time is still in the future.
                        // This lets the web client know that a scheduled maintenance window is upcoming.
                        $setting_res['web_settings']['customer_web_scheduled_maintenance_mode'] = "1";
                    }
                } catch (\Exception $e) {
                    // In case of any parsing / timezone error, we keep the default values.
                    // This avoids breaking the API response for bad or unexpected date formats.
                }
            }

            // Decide if the web is currently in maintenance mode. Same logic as customer_app_maintenance_mode: compare in UTC and set "1" or "0".
            if (isset($setting_res['web_settings']['customer_web_maintenance_mode']) && $setting_res['web_settings']['customer_web_maintenance_mode'] == 1) {
                try {
                    $utcTimeZoneObj = new \DateTimeZone('UTC');
                    $nowUtc = new \DateTime('now', $utcTimeZoneObj);
                    $startUtc = new \DateTime($customer_web_maintenance_mode_start_date, $utcTimeZoneObj);
                    $endUtc = new \DateTime($customer_web_maintenance_mode_end_date, $utcTimeZoneObj);
                    if (($nowUtc >= $startUtc) && ($nowUtc <= $endUtc)) {
                        $setting_res['web_settings']['customer_web_maintenance_mode'] = "1";
                    } else {
                        $setting_res['web_settings']['customer_web_maintenance_mode'] = "0";
                    }
                } catch (\Exception $e) {
                    $setting_res['web_settings']['customer_web_maintenance_mode'] = "0";
                }
            } else {
                $setting_res['web_settings']['customer_web_maintenance_mode'] = "0";
            }

            // Parse web_maintenance_mode schedule date range (same pattern as customer_web_maintenance_mode).
            // Toggle web_maintenance_mode to 0 or 1 (int) based on whether current time is within the schedule.
            $web_maintenance_mode_schedule_date = isset($setting_res['web_settings']['web_maintenance_schedule_date'])
                ? explode("to", $setting_res['web_settings']['web_maintenance_schedule_date'])
                : null;
            if (!empty($web_maintenance_mode_schedule_date)) {
                $web_maintenance_mode_start_date = isset($web_maintenance_mode_schedule_date[0]) ? trim($web_maintenance_mode_schedule_date[0]) : "";
                $web_maintenance_mode_end_date = isset($web_maintenance_mode_schedule_date[1]) ? trim($web_maintenance_mode_schedule_date[1]) : "";
            } else {
                $web_maintenance_mode_start_date = null;
                $web_maintenance_mode_end_date = null;
            }
            if (
                isset($setting_res['web_settings']['web_maintenance_mode']) && $setting_res['web_settings']['web_maintenance_mode'] == 1
                && !empty($web_maintenance_mode_start_date) && !empty($web_maintenance_mode_end_date)
            ) {
                $today = strtotime(date('Y-m-d H:i'));
                $start_time = strtotime(date('Y-m-d H:i', strtotime($web_maintenance_mode_start_date)));
                $expiry_time = strtotime(date('Y-m-d H:i', strtotime($web_maintenance_mode_end_date)));
                if (($today >= $start_time) && ($today <= $expiry_time)) {
                    $setting_res['web_settings']['web_maintenance_mode'] = 1;
                } else {
                    $setting_res['web_settings']['web_maintenance_mode'] = 0;
                }
            } else {
                $setting_res['web_settings']['web_maintenance_mode'] = 0;
            }

            $imageSettings = ['favicon', 'logo', 'half_logo', 'partner_favicon', 'partner_logo', 'partner_half_logo'];
            $fileService = service('fileService');
            foreach ($imageSettings as $key) {
                if (isset($setting_res['general_settings'][$key])) {
                    $setting_res['general_settings'][$key] = $fileService->url($setting_res['general_settings'][$key], 'site');
                }
            }

            if (isset($setting_res['general_settings']['provider_location_in_provider_details']) && $setting_res['general_settings']['provider_location_in_provider_details'] == 1) {
                $setting_res['general_settings']['provider_location_in_provider_details'] = "1";
            } else {
                $setting_res['general_settings']['provider_location_in_provider_details'] = "0";
            }
            $WebimageSettings = ['web_logo', 'web_favicon', 'footer_logo', 'landing_page_logo', 'landing_page_backgroud_image', 'web_half_logo', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image'];
            foreach ($WebimageSettings as $key) {
                if (isset($setting_res['web_settings'][$key])) {
                    $setting_res['web_settings'][$key] = $fileService->url($setting_res['web_settings'][$key], 'web_settings');
                }
            }
            if (!empty($setting_res['web_settings']['social_media'])) {
                foreach ($setting_res['web_settings']['social_media'] as &$row) {
                    $row['file'] = !empty($row['file']) ? $fileService->url($row['file'], 'web_settings') : "";
                }
            } else {
                $setting_res['web_settings']['social_media'] = [];
            }
            $setting_res['server_time'] = $this->toDateTime;
            $setting_res['general_settings']['demo_mode'] = (ALLOW_MODIFICATION == 1) ? "0" : "1";
            if ($setting_res['general_settings']['demo_mode'] === '1') {
                $current = $setting_res['general_settings']['default_country_code'] ?? '';
                if ($current !== 'IN') {
                    $setting_res['general_settings']['default_country_code'] = 'IN';
                }
            }
            // Mirror currency from general_settings into app_settings for mobile backward compatibility.
            foreach (['country_currency_code', 'currency', 'decimal_point'] as $currencyKey) {
                $setting_res['app_settings'][$currencyKey] = $setting_res['general_settings'][$currencyKey] ?? '';
            }
            $keys_to_unset = ['become_provider_page_settings', 'sms_gateway_setting', 'notification_settings', 'firebase_settings', 'country_codes_old'];
            foreach ($keys_to_unset as $key) {
                if (array_key_exists($key, $setting_res)) {
                    unset($setting_res[$key]);
                }
            }
            //for web landing page settings
            $web_landing_page_keys = ['landing_page_backgroud_image', 'landing_page_logo', 'landing_page_title', 'category_section_status', 'category_section_title', 'category_section_description', 'rating_section_status', 'rating_section_title', 'rating_section_description', 'process_flow_status', 'process_flow_title', 'process_flow_description', 'faq_section_status', 'faq_section_title', 'faq_section_description'];

            foreach ($web_landing_page_keys as $key) {
                $setting_res['web_settings'][$key] = isset($setting_res['web_settings'][$key]) ? $setting_res['web_settings'][$key] : "";
                unset($setting_res['web_settings'][$key]);
            }

            $become_provider_page_settings = get_settings('become_provider_page_settings', true);

            $sections = [
                'hero_section',
                'category_section',
                'subscription_section',
                'top_providers_section',
                'review_section',
                'faq_section',
                'feature_section',
                'how_it_work_section'
            ];

            $show_become_provider_page = false;
            foreach ($sections as $section) {
                if (isset($become_provider_page_settings[$section])) {
                    $section_value = $become_provider_page_settings[$section];

                    // Decode only if it's a string
                    $section_data = is_array($section_value)
                        ? $section_value
                        : json_decode($section_value, true);

                    if (isset($section_data['status']) && $section_data['status'] == 1) {
                        $show_become_provider_page = true;
                        break;
                    }
                }
            }

            $setting_res['web_settings']['show_become_provider_page'] = $show_become_provider_page;

            $setting_res['available_country_codes'] = $this->fetch_country_codes();

            // Get requested language from Content-Language header
            $requestedLanguage = get_current_language_from_request();

            // Format translatable settings with language support
            // This adds translated_ prefixed fields for about_us, terms_conditions, privacy_policy etc.
            $multilingual_fields = ['about_us', 'terms_conditions', 'privacy_policy', 'customer_terms_conditions', 'customer_privacy_policy', 'contact_us'];

            foreach ($multilingual_fields as $field_name) {
                if (isset($setting_res[$field_name])) {
                    // Transform the field and merge results into setting_res
                    $transformed_field = $this->transformMultilingualField($setting_res, $field_name, $requestedLanguage);
                    $setting_res = array_merge($setting_res, $transformed_field);
                }
            }

            // Transform general_settings multilingual fields
            // This handles fields like company_title, copyright_details, address, short_description etc.
            // Note: message_for_customer_application and message_for_provider_application are moved to app_settings before transformation
            if (isset($setting_res['general_settings'])) {
                $setting_res['general_settings'] = $this->transformGeneralSettingsMultilingualFields($setting_res['general_settings'], $requestedLanguage);
            }

            // Transform app_settings multilingual fields
            // This handles fields like message_for_customer_application, message_for_provider_application
            // These fields are moved from general_settings to app_settings before transformation
            if (isset($setting_res['app_settings'])) {
                $setting_res['app_settings'] = $this->transformAppSettingsMultilingualFields($setting_res['app_settings'], $requestedLanguage);
            }

            // Transform web_settings multilingual fields
            // This handles fields like web_title, cookie_consent_title, etc.
            if (isset($setting_res['web_settings'])) {
                $setting_res['web_settings'] = $this->transformWebSettingsMultilingualFields($setting_res['web_settings'], $requestedLanguage);

                // Group flat theme color keys into a nested theme object
                $ws = &$setting_res['web_settings'];
                $ws['theme'] = [
                    'light' => [
                        'primary_color' => $ws['light_primary_color'] ?? '',
                        'secondary_color' => $ws['light_secondary_color'] ?? '',
                        'light_bg_color' => $ws['light_bg_color'] ?? '',
                        'text_color' => $ws['light_text_color'] ?? '',
                        'card_background_color' => $ws['light_card_bg_color'] ?? '',
                        'description_text_color' => $ws['light_description_text_color'] ?? '',
                    ],
                    'dark' => [
                        'primary_color' => $ws['dark_primary_color'] ?? '',
                        'secondary_color' => $ws['dark_secondary_color'] ?? '',
                        'light_bg_color' => $ws['dark_bg_color'] ?? '',
                        'text_color' => $ws['dark_text_color'] ?? '',
                        'card_background_color' => $ws['dark_card_bg_color'] ?? '',
                        'description_text_color' => $ws['dark_description_text_color'] ?? '',
                    ],
                ];
                unset(
                    $ws['light_primary_color'],
                    $ws['light_secondary_color'],
                    $ws['light_bg_color'],
                    $ws['light_text_color'],
                    $ws['light_card_bg_color'],
                    $ws['light_description_text_color'],
                    $ws['dark_primary_color'],
                    $ws['dark_secondary_color'],
                    $ws['dark_bg_color'],
                    $ws['dark_text_color'],
                    $ws['dark_card_bg_color'],
                    $ws['dark_description_text_color']
                );
                unset($ws);
            }

            if (isset($setting_res['system_tax_settings'])) {
                $setting_res['system_tax_settings']['show_on_checkout'] = $setting_res['system_tax_settings']['show_on_checkout'] == 1 ? "1" : "0";
            }

            $default_language = [];
            try {
                $languageModel = new Language_model();
                $default_language_result = $languageModel->select('id, language, code, is_rtl, is_default, image')
                    ->where('is_default', 1)
                    ->get()
                    ->getRowArray();

                if ($default_language_result) {
                    $fileService = service('fileService');
                    $default_language = [
                        'code' => $default_language_result['code'],
                        'name' => $default_language_result['language'],
                        'is_rtl' => $default_language_result['is_rtl'],
                        'image' => $fileService->url($default_language_result['image'], 'language_image'),
                    ];
                }
                $setting_res['default_language'] = $default_language;
            } catch (\Exception $th) {
                log_message('error', 'Error getting default language: ' . $th);
            }

            if (isset($setting_res) && !empty($setting_res)) {
                $response = [
                    'error' => false,
                    'message' => labels(SETTING_RECIEVED_SUCCESSFULLY, "setting recieved Successfully"),
                    'data' => $setting_res,
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND_IN_SETTING, "No data found in setting"),
                    'data' => $setting_res,
                ];
            }
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_settings()');
            return $this->response->setJSON($response);
        }
    }

    public function get_web_landing_page_settings()
    {
        $web_settings = get_settings('web_settings', true);

        // Only fetch and expose section data when the section status is active ("1").
        // This ensures the API returns only active sections; disabled sections are not visible.
        $categorySectionActive = isset($web_settings['category_section_status']) && $web_settings['category_section_status'] == "1";
        $ratingSectionActive = isset($web_settings['rating_section_status']) && $web_settings['rating_section_status'] == "1";
        $processFlowActive = isset($web_settings['process_flow_status']) && $web_settings['process_flow_status'] == "1";

        // Fetch Categories only when category section is active
        $categories_ids = $categorySectionActive ? ($web_settings['category_ids'] ?? []) : [];
        $categories = [];
        $fileService = service('fileService');
        if (!empty($categories_ids)) {
            $categories_data = fetch_details('categories', [], [], '', '', '', '', 'id', $categories_ids);
            foreach ($categories_data as &$row) {
                $row['image'] = (!empty($row['image']) && $fileService->exists('categories', $row['image']))
                    ? $fileService->url($row['image'], 'categories')
                    : '';
            }
            $categories = $categories_data;
        }

        // Fetch Ratings only when rating section is active
        $ratings = [];
        $db = \Config\Database::connect();
        if ($ratingSectionActive && !empty($web_settings['rating_ids'])) {
            $rating_ids = is_array($web_settings['rating_ids']) && isset($web_settings['rating_ids'][0])
                ? explode(',', (string) $web_settings['rating_ids'][0])
                : (array) ($web_settings['rating_ids'] ?? []);
            foreach ($rating_ids as $id) {
                $row1 = $db->table('services_ratings sr')
                    ->select('sr.id, sr.rating, sr.comment, sr.created_at as rated_on, sr.images, u.image as profile_image, u.username as user_name, sr.custom_job_request_id, s.title as service_name, s.user_id as partner_id')
                    ->join('users u', 'u.id = sr.user_id')
                    ->join('services s', 's.id = sr.service_id')
                    ->where('sr.id', $id)
                    ->get()
                    ->getRowArray();
                if ($row1) {
                    $profileImagePath = $fileService->url($row1['profile_image'] ?? '', 'profile', 'public/uploads/profiles/default.png');
                    $images = $row1['images'] ? rating_images($row1['id'], true) : [];
                    $ratings[] = [
                        'id' => $row1['id'],
                        'rating' => $row1['rating'],
                        'comment' => $row1['comment'],
                        'user_name' => $row1['user_name'],
                        'service_name' => $row1['service_name'],
                        'rated_on' => $row1['rated_on'],
                        'partner_name' => $this->getPartnerName($row1['partner_id']),
                        'profile_image' => $profileImagePath,
                        'images' => $images,
                    ];
                }
            }
            $web_settings['ratings'] = $ratings;
        }
        $web_settings['categories'] = $categories;
        $web_settings['ratings'] = $ratings;
        $image_keys = [
            'web_logo',
            'web_favicon',
            'footer_logo',
            'landing_page_logo',
            'landing_page_backgroud_image',
            'web_half_logo',
            'step_1_image',
            'step_2_image',
            'step_3_image',
            'step_4_image'
        ];
        foreach ($image_keys as $key) {
            if (isset($web_settings[$key])) {
                $web_settings[$key] = $fileService->url($web_settings[$key], 'web_settings');
            } else {
                $web_settings[$key] = '';
            }
        }
        $title_keys = [
            'step_1_title',
            'step_2_title',
            'step_3_title',
            'step_4_title'
        ];
        $description_keys = [
            'step_1_description',
            'step_2_description',
            'step_3_description',
            'step_4_description'
        ];
        $process_flow_images_keys = [
            'step_1_image',
            'step_2_image',
            'step_3_image',
            'step_4_image'
        ];
        // Build process_flow_data only when process flow section is active; otherwise leave empty and unset step keys
        $web_settings['process_flow_data'] = [];
        if ($processFlowActive) {
            $num_steps = count($title_keys);
            for ($i = 0; $i < $num_steps; $i++) {
                $title_key = $title_keys[$i];
                $description_key = $description_keys[$i];
                $image_key = $process_flow_images_keys[$i];
                $web_settings['process_flow_data'][] = [
                    'id' => $i + 1,
                    'title' => $web_settings[$title_key] ?? '',
                    'description' => $web_settings[$description_key] ?? '',
                    'image' => $web_settings[$image_key] ?? '',
                ];
                unset($web_settings[$title_key], $web_settings[$description_key], $web_settings[$image_key]);
            }
        } else {
            // Section disabled: do not expose step_* keys in API
            foreach (array_merge($title_keys, $description_keys, $process_flow_images_keys) as $key) {
                unset($web_settings[$key]);
            }
        }
        if (isset($web_settings['faq_section_status']) && $web_settings['faq_section_status'] == "1") {
            // Get language code from request header for FAQ translations (same as get_faqs API)
            $requested_language = get_current_language_from_request();
            $default_language = get_default_language();

            // Fetch FAQs with translation support (same logic as get_faqs API)
            $Faqs_model = new Faqs_model();
            $faq_data = $Faqs_model->list(true, '', 50, 0, 'id', 'ASC'); // Get up to 50 FAQs for landing page

            if (!empty($faq_data['data'])) {
                // Get all FAQ IDs for batch translation lookup
                $faq_ids = array_column($faq_data['data'], 'id');
                // Initialize translation lookup array
                $translation_lookup = [];

                // Try to fetch translations if translation table exists (backward compatibility)
                try {
                    $db = \Config\Database::connect();
                    $builder = $db->table('translated_faq_details');

                    // Get unique language codes to avoid duplicates
                    $language_codes = array_unique([$default_language, $requested_language]);
                    $translations = $builder->select('faq_id, language_code, question, answer')
                        ->whereIn('faq_id', $faq_ids)
                        ->whereIn('language_code', $language_codes)
                        ->get()
                        ->getResultArray();

                    // Organize translations by FAQ ID and language for easy lookup
                    foreach ($translations as $translation) {
                        // Ensure FAQ ID is treated as integer for consistent array key matching
                        $faq_id_key = (int) $translation['faq_id'];
                        $translation_lookup[$faq_id_key][$translation['language_code']] = [
                            'question' => $translation['question'],
                            'answer' => $translation['answer']
                        ];
                    }
                } catch (\Exception $e) {
                    // Translation table doesn't exist yet, continue without translations
                    log_message('debug', 'Translation table not found in get_web_landing_page_settings, using main table values only. Error: ' . $e->getMessage());
                }

                // Process each FAQ to add translation support
                $processed_faqs = [];
                foreach ($faq_data['data'] as $faq) {
                    $faq_id = (int) $faq['id']; // Ensure FAQ ID is integer for consistent array key matching

                    // Get translations from lookup (avoid individual database queries)
                    $default_translation = isset($translation_lookup[$faq_id][$default_language])
                        ? $translation_lookup[$faq_id][$default_language]
                        : null;
                    $requested_translation = isset($translation_lookup[$faq_id][$requested_language])
                        ? $translation_lookup[$faq_id][$requested_language]
                        : null;

                    // Build response with proper fallback logic
                    $processed_faq = [
                        'id' => $faq['id'],
                        'status' => $faq['status'],
                        'created_at' => $faq['created_at']
                    ];

                    // Question field: Always use default language translation or fallback to main table
                    // This ensures consistent default language content in the main question field
                    if ($default_translation && !empty($default_translation['question'])) {
                        $processed_faq['question'] = $default_translation['question'];
                    } else {
                        $processed_faq['question'] = $faq['question'];
                    }

                    // Answer field: Always use default language translation or fallback to main table
                    // This ensures consistent default language content in the main answer field
                    if ($default_translation && !empty($default_translation['answer'])) {
                        $processed_faq['answer'] = $default_translation['answer'];
                    } else {
                        $processed_faq['answer'] = $faq['answer'];
                    }

                    // Translated question: Fallback hierarchy for requested language
                    // 1. Requested language translation (if exists and not empty)
                    // 2. Default language translation (if exists and not empty)
                    // 3. Main table value (final fallback)
                    if ($requested_translation && !empty($requested_translation['question'])) {
                        $processed_faq['translated_question'] = $requested_translation['question'];
                    } elseif ($default_translation && !empty($default_translation['question'])) {
                        $processed_faq['translated_question'] = $default_translation['question'];
                    } else {
                        $processed_faq['translated_question'] = $faq['question'];
                    }

                    // Translated answer: Fallback hierarchy for requested language
                    // 1. Requested language translation (if exists and not empty)
                    // 2. Default language translation (if exists and not empty)
                    // 3. Main table value (final fallback)
                    if ($requested_translation && !empty($requested_translation['answer'])) {
                        $processed_faq['translated_answer'] = $requested_translation['answer'];
                    } elseif ($default_translation && !empty($default_translation['answer'])) {
                        $processed_faq['translated_answer'] = $default_translation['answer'];
                    } else {
                        $processed_faq['translated_answer'] = $faq['answer'];
                    }

                    $processed_faqs[] = $processed_faq;
                }

                $web_settings['faqs'] = $processed_faqs;
            } else {
                $web_settings['faqs'] = [];
            }
        } else {
            $web_settings['faqs'] = [];
        }
        //for web settings
        $web_landing_page_keys = [
            'web_favicon',
            'web_half_logo',
            'web_logo',
            'web_title',
            'playstore_url',
            'footer_description',
            'footer_logo',
            'applestore_url',
        ];
        //web settings
        foreach ($web_landing_page_keys as $key) {
            $web_settings[$key] = isset($web_settings[$key]) ? $web_settings[$key] : "";
            unset($web_settings[$key]);
        }

        // Get default language information - similar to get_language_list API
        $default_language = [];
        try {
            $languageModel = new Language_model();
            $default_language_result = $languageModel->select('id, language, code, is_rtl, is_default, image')
                ->where('is_default', 1)
                ->get()
                ->getRowArray();

            if ($default_language_result) {
                $fileService = service('fileService');
                $default_language = [
                    'code' => $default_language_result['code'],
                    'name' => $default_language_result['language'],
                    'is_rtl' => $default_language_result['is_rtl'],
                    'image' => $fileService->url($default_language_result['image'], 'language_image'),
                ];
            }
            $web_settings['default_language'] = $default_language;
        } catch (\Throwable $th) {
            // Log error but don't fail the entire API
            log_the_responce(
                'Error fetching default language in get_web_landing_page_settings: ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_web_landing_page_settings()'
            );
        }

        // Apply multilingual transformation to web landing page settings
        $web_settings = $this->transformWebLandingPageMultilingualFields($web_settings);

        // Remove disabled sections from the response so only active sections are visible in the API.
        // Unset section status, title, description (and translated_* variants) and section data for disabled sections.
        if (!$categorySectionActive) {
            $web_settings['categories'] = [];
            foreach (['category_section_status', 'category_section_title', 'category_section_description'] as $k) {
                unset($web_settings[$k], $web_settings['translated_' . $k]);
            }
        }
        if (!$ratingSectionActive) {
            $web_settings['ratings'] = [];
            foreach (['rating_section_status', 'rating_section_title', 'rating_section_description'] as $k) {
                unset($web_settings[$k], $web_settings['translated_' . $k]);
            }
        }
        if (!$processFlowActive) {
            $web_settings['process_flow_data'] = [];
            foreach (['process_flow_status', 'process_flow_title', 'process_flow_description'] as $k) {
                unset($web_settings[$k], $web_settings['translated_' . $k]);
            }
        }
        $faqSectionActive = isset($web_settings['faq_section_status']) && $web_settings['faq_section_status'] == "1";
        if (!$faqSectionActive) {
            $web_settings['faqs'] = [];
            foreach (['faq_section_status', 'faq_section_title', 'faq_section_description'] as $k) {
                unset($web_settings[$k], $web_settings['translated_' . $k]);
            }
        }

        $response = [
            'error' => empty($web_settings),
            'message' => empty($web_settings) ? labels(NO_DATA_FOUND_IN_SETTING, 'No data found in setting') : labels(SETTINGS_RECEIVED_SUCCESSFULLY, 'Settings received successfully'),
            'data' => $web_settings,
            // 'default_language' => $default_language, // Add default language object to response
        ];
        return $this->response->setJSON($response);
    }

    public function get_become_provider_settings()
    {
        $db = \Config\Database::connect();
        $happyCustomers = $db->table('users u')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 2)
            ->select('COUNT(u.id) as total')
            ->get()
            ->getRowArray()['total'];
        $become_provider_settings = [];
        $become_provider_settings['happyCustomers'] = $happyCustomers;
        $ratingData = $db->table('services_ratings sr')
            ->select('
                        COUNT(sr.rating) as number_of_rating,
                        SUM(sr.rating) as total_rating,
                        (SUM(sr.rating) / COUNT(sr.rating)) as average_rating
                    ')
            ->join('services s', 'sr.service_id = s.id', 'left')
            ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
            ->join('partner_bids pd', 'pd.custom_job_request_id = cj.id', 'left')
            ->get()->getResultArray();
        $become_provider_settings['rating'] = isset($ratingData[0]['average_rating']) ? $ratingData[0]['average_rating'] : "0";

        // Get default language info for response
        $default_language = [];
        try {
            $languageModel = new Language_model();
            $default_language_result = $languageModel->select('id, language, code, is_rtl, is_default, image')
                ->where('is_default', 1)
                ->get()
                ->getRowArray();

            if ($default_language_result) {
                $fileService = service('fileService');
                $default_language = [
                    'code' => $default_language_result['code'],
                    'name' => $default_language_result['language'],
                    'is_rtl' => $default_language_result['is_rtl'],
                    'image' => $fileService->url($default_language_result['image'], 'language_image'),
                ];
            }
            $become_provider_settings['default_language'] = $default_language;
        } catch (\Throwable $th) {
            // Log error but don't fail the entire API
            log_the_responce(
                'Error fetching default language in get_become_provider_settings: ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_become_provider_settings()'
            );
        }

        $become_provider_page_settings = get_settings('become_provider_page_settings', true);
        $sections = [
            'hero_section',
            'category_section',
            'subscription_section',
            'top_providers_section',
            'review_section',
            'faq_section',
            'feature_section',
            'how_it_work_section'
        ];
        foreach ($sections as $section) {
            if (isset($become_provider_page_settings[$section])) {
                // Handle backward compatibility - support both JSON string and array formats
                if (is_string($become_provider_page_settings[$section])) {
                    // If it's a JSON string, decode it
                    $become_provider_settings[$section] = json_decode($become_provider_page_settings[$section], true);
                } else if (is_array($become_provider_page_settings[$section])) {
                    // If it's already an array, use it directly
                    $become_provider_settings[$section] = $become_provider_page_settings[$section];
                } else {
                    // Fallback for other data types
                    $become_provider_settings[$section] = [];
                }
            }
        }
        // Unset sections with status == 0
        foreach ($become_provider_settings as $section => $settings) {
            if (isset($settings['status']) && $settings['status'] == 0) {
                unset($become_provider_settings[$section]);
            }
        }
        if (isset($become_provider_settings['hero_section']['status']) && $become_provider_settings['hero_section']['status'] == 1) {
            if (isset($become_provider_settings['hero_section']['images'])) {
                $images = $become_provider_settings['hero_section']['images'];
                $fileService = service('fileService');
                foreach ($images as &$image) {
                    if (!isset($image['image'])) {
                        $image['image'] = "";
                        continue;
                    }
                    $image['image'] = $fileService->url($image['image'], 'become_provider');
                }
                unset($image); // Unset reference to avoid potential issues
                $become_provider_settings['hero_section']['images'] = $images;
            }
        }
        $fileService = service('fileService');
        if (isset($become_provider_settings['feature_section']['status']) && ($become_provider_settings['feature_section']['status'] == 1)) {
            if (isset($become_provider_settings['feature_section']['features'])) {
                $features = $become_provider_settings['feature_section']['features'];
                // Iterate using reference to modify the original array
                foreach ($features as $key => &$feature) { // Add '&' to pass by reference
                    // Empty image falls back to default placeholder automatically
                    $feature['image'] = $fileService->url($feature['image'] ?? '', 'become_provider');
                }
                // Assign updated features back
                $become_provider_settings['feature_section']['features'] = $features;
            }
        }
        if (isset($become_provider_settings['how_it_work_section']['status']) && ($become_provider_settings['how_it_work_section']['status'] == 1)) {
            // Process the how_it_work_section's steps with backward compatibility
            if (isset($become_provider_settings['how_it_work_section']['steps'])) {
                if (is_string($become_provider_settings['how_it_work_section']['steps'])) {
                    // Old format: double-encoded JSON string, decode it
                    $become_provider_settings['how_it_work_section']['steps'] = json_decode($become_provider_settings['how_it_work_section']['steps'], true) ?: [];
                }
                // If already an array (new format), leave it as is
            }
        }
        if (isset($become_provider_settings['category_section']['status']) && ($become_provider_settings['category_section']['status'] == 1)) {
            // Process category_section with categories
            $category_section = $become_provider_settings['category_section'] ?? [];
            $category_section['category_ids'] = $category_section['category_ids'] ?? [];

            if (!empty($category_section['category_ids'])) {
                // Ensure category_ids is an array for whereIn clause
                $category_ids = $category_section['category_ids'];

                // Handle different possible formats of category_ids
                if (is_string($category_ids)) {
                    // If it's a comma-separated string, convert to array
                    $category_ids = array_filter(array_map('trim', explode(',', $category_ids)));
                } elseif (!is_array($category_ids)) {
                    // If it's neither string nor array, convert to array
                    $category_ids = [$category_ids];
                }

                // Remove empty values and convert to integers
                $category_ids = array_filter(array_map('intval', $category_ids), function ($id) {
                    return $id > 0;
                });

                if (!empty($category_ids)) {
                    $categories = fetch_details('categories', [], ['id', 'image', 'slug', 'name'], '', '', '', '', 'id', $category_ids);
                } else {
                    $categories = [];
                }
            } else {
                $categories = [];
            }
            foreach ($categories as &$category) {
                // Add translated category name using existing translation system
                if (isset($category['id'])) {
                    // Use existing helper function to get translated category data
                    $translatedData = get_translated_category_data_for_api($category['id'], $category);
                    // Merge translated data with original category data
                    $category = array_merge($category, $translatedData);
                }

                // Resolve category image; empty/missing returns ''
                $category['category'] = (!empty($category['image']) && $fileService->exists('categories', $category['image']))
                    ? $fileService->url($category['image'], 'categories')
                    : '';
            }
            // Remove reference to avoid potential issues with further manipulation
            unset($category);
            $category_section['categories'] = array_merge($category_section['categories'] ?? [], $categories);
            $become_provider_settings['category_section'] = $category_section;
        }
        if (isset($become_provider_settings['subscription_section']['status']) && ($become_provider_settings['subscription_section']['status'] == 1)) {
            // Process subscription_section with subscriptions
            $subscription_section = $become_provider_settings['subscription_section'] ?? [];
            $subscriptions = fetch_details('subscriptions', ['status' => 1, 'publish' => 1]);

            // Add translated subscription names and descriptions
            foreach ($subscriptions as &$subscription) {
                if (isset($subscription['id'])) {
                    // Use existing helper function to get translated subscription data
                    $translatedData = $this->getTranslatedSubscriptionData($subscription['id'], $subscription);
                    // Merge translated data with original subscription data
                    $subscription = array_merge($subscription, $translatedData);
                }
            }
            // Remove reference to avoid potential issues
            unset($subscription);

            $subscription_section['subscriptions'] = array_merge($subscription_section['subscriptions'] ?? [], $subscriptions);
            $become_provider_settings['subscription_section'] = $subscription_section;
        }
        if (isset($become_provider_settings['faq_section']['status']) && ($become_provider_settings['faq_section']['status'] == 1)) {
            // Process faq_section with faqs
            $faq_section = $become_provider_settings['faq_section'] ?? [];

            // Get FAQs and decode from string to array if needed
            $faqs = $faq_section['faqs'] ?? [];

            if (is_string($faqs)) {
                // Handle double JSON encoding - decode twice if needed
                $faqs = json_decode($faqs, true);
                // Check if the result is still a JSON string (double encoding)
                if (is_string($faqs)) {
                    $faqs = json_decode($faqs, true);
                }
                // Final check - if still not an array, log the issue
                if (!is_array($faqs)) {
                    log_the_responce(
                        'FAQ data could not be decoded properly. Raw data: ' . substr($faq_section['faqs'] ?? '', 0, 200),
                        date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_become_provider_settings() FAQ processing'
                    );
                    $faqs = [];
                }
            }

            $faqs = is_array($faqs) ? $faqs : [];

            // Process FAQ structure - handle both multilingual and simple formats
            $processed_faqs = [];
            if (!empty($faqs)) {
                foreach ($faqs as $faq) {
                    if (isset($faq['question']) && isset($faq['answer'])) {
                        // Handle multilingual structure: {"question": {"en": "text", "hi": "text"}}
                        if (is_array($faq['question']) && is_array($faq['answer'])) {
                            // Get default language content
                            $default_language = get_default_language();
                            $requested_language = get_current_language_from_request();

                            // Get question in requested language, fallback to default
                            $question = $faq['question'][$requested_language] ?? $faq['question'][$default_language] ?? '';
                            if (empty($question)) {
                                // If still empty, get the first available language
                                $question = reset($faq['question']) ?: '';
                            }

                            // Get answer in requested language, fallback to default
                            $answer = $faq['answer'][$requested_language] ?? $faq['answer'][$default_language] ?? '';
                            if (empty($answer)) {
                                // If still empty, get the first available language
                                $answer = reset($faq['answer']) ?: '';
                            }

                            $processed_faqs[] = [
                                'question' => $question,
                                'answer' => $answer
                            ];
                        } else {
                            // Handle simple structure (backward compatibility)
                            $processed_faqs[] = [
                                'question' => $faq['question'] ?? '',
                                'answer' => $faq['answer'] ?? ''
                            ];
                        }
                    }
                }
            }

            // If no FAQs were processed, try to get them from the raw section data
            if (empty($processed_faqs) && !empty($faq_section)) {
                // Check if FAQs are stored directly in the section
                if (isset($faq_section['faqs']) && is_array($faq_section['faqs'])) {
                    $processed_faqs = $faq_section['faqs'];
                }
            }

            $become_provider_settings['faq_section'] = $faq_section;
            $become_provider_settings['faq_section']['faqs'] = $processed_faqs;
        }
        if (isset($become_provider_settings['review_section']['status']) && ($become_provider_settings['review_section']['status'] == 1)) {

            $review_section = $become_provider_settings['review_section'] ?? [];

            if (isset($review_section['rating_ids']) && !empty($review_section['rating_ids'])) {

                // Handle different possible formats of rating_ids
                $rating_ids_raw = $review_section['rating_ids'];
                if (is_string($rating_ids_raw)) {
                    // If it's a string, split by comma
                    $rating_ids = explode(',', $rating_ids_raw);
                } else if (is_array($rating_ids_raw) && isset($rating_ids_raw[0])) {
                    // If it's an array with first element being a string
                    $rating_ids = is_array($rating_ids_raw[0]) ? $rating_ids_raw[0] : explode(',', $rating_ids_raw[0]);
                } else if (is_array($rating_ids_raw)) {
                    // If it's already an array of IDs
                    $rating_ids = $rating_ids_raw;
                } else {
                    $rating_ids = [];
                }

                // Clean up and convert to integers
                $rating_ids = array_filter(array_map('trim', $rating_ids));
                $rating_ids = array_map('intval', $rating_ids);
                $rating_ids = array_filter($rating_ids, function ($id) {
                    return $id > 0;
                });


                $db = \Config\Database::connect();
                $builder = $db->table('services_ratings sr');
                $builder->select(
                    'sr.*,
                     u.image as profile_image,
                     u.username, 
                     COALESCE(s.user_id, pb.partner_id) as partner_id,
                     COALESCE(s.title, cj.service_title) as service_name,
                     COALESCE(partner_user.username, "") as partner_name'
                )
                    ->join('users u', 'u.id = sr.user_id')
                    ->join('services s', 's.id = sr.service_id', 'left')
                    ->join('custom_job_requests cj', 'cj.id = sr.custom_job_request_id', 'left')
                    ->join('partner_bids pb', 'pb.custom_job_request_id = cj.id', 'left')
                    ->join('users partner_user', 'partner_user.id = COALESCE(s.user_id, pb.partner_id)', 'left')
                    ->whereIn('sr.id', $rating_ids)
                    ->orderBy('sr.id', 'DESC');
                $reviews = $builder->get()->getResultArray();

                $review_section['reviews'] = array_merge($review_section['reviews'] ?? [], $reviews);
                // Process the reviews with proper null handling
                if (!empty($reviews)) {
                    foreach ($reviews as &$review) {
                        // Resolve profile image with default fallback
                        $review['profile_image'] = $fileService->url($review['profile_image'] ?? '', 'profile', 'public/uploads/profiles/default.png');

                        // Handle rating images
                        if (!empty($review['images'])) {
                            $images = rating_images($review['id'], true);
                            $review['images'] = $images;
                        } else {
                            $review['images'] = [];
                        }

                        // Format created_at date
                        if (isset($review['created_at'])) {
                            $review['formatted_date'] = date('j M Y, g:i A', strtotime($review['created_at']));
                        }

                        // Ensure all required fields are present with proper defaults
                        $review['id'] = (int) ($review['id'] ?? 0);
                        $review['rating'] = (float) ($review['rating'] ?? 0);
                        $review['comment'] = $review['comment'] ?? '';
                        $review['username'] = $review['username'] ?? '';
                        $review['service_name'] = $review['service_name'] ?? '';
                        $review['partner_name'] = $review['partner_name'] ?? '';

                        // Get translated service name from translated_service_details table
                        if (!empty($review['service_id']) && !empty($review['service_name'])) {
                            $translatedServiceData = $this->getTranslatedServiceTitle($review['service_id'], $review['service_name']);
                            $review['translated_service_name'] = $translatedServiceData['translated_title'];
                        } else {
                            $review['translated_service_name'] = $review['service_name'] ?? '';
                        }
                    }
                    unset($review); // Unset reference to prevent unintended modifications

                    // Store the processed reviews
                    $review_section['reviews'] = $reviews;
                }
                $become_provider_settings['review_section'] = $review_section;
            } else {
                $become_provider_settings['review_section'] = $review_section;
            }
        }
        if (isset($become_provider_settings['top_providers_section']['status']) && ($become_provider_settings['top_providers_section']['status'] == 1)) {
            $top_providers_section = $become_provider_settings['top_providers_section'] ?? [];
            $rated_data = get_top_rated_providers();

            // Get translations from proper translation tables
            foreach ($rated_data as &$provider) {
                // Get translated company name from translated_partner_details table
                if (isset($provider['id']) && !empty($provider['company_name'])) {
                    $translatedCompanyName = $this->getTranslatedPartnerCompanyName($provider['id'], $provider['company_name']);
                    $provider['translated_company_name'] = $translatedCompanyName;
                }

                // Get translated service titles from translated_service_details table
                if (isset($provider['services']) && is_array($provider['services'])) {
                    foreach ($provider['services'] as &$service) {
                        if (!empty($service['title'])) {
                            // Get service ID by title and provider ID
                            $serviceId = $this->getServiceIdByTitleAndProviderId($service['title'], $provider['id']);
                            if ($serviceId) {
                                $service['id'] = $serviceId;
                                $translatedServiceData = $this->getTranslatedServiceTitle($serviceId, $service['title']);
                                $service['translated_title'] = $translatedServiceData['translated_title'];
                            } else {
                                // Fallback to original title if service ID not found
                                $service['translated_title'] = $service['title'];
                            }
                        }
                    }
                    unset($service); // Remove reference
                }
            }
            unset($provider); // Remove reference

            $top_providers_section['providers'] = array_merge($top_providers_section['providers'] ?? [], $rated_data);
            $become_provider_settings['top_providers_section'] = $top_providers_section;
        }

        // Apply multilingual transformation to become provider settings
        $become_provider_settings = $this->transformBecomeProviderMultilingualFields($become_provider_settings);

        // Response
        $response = [
            'error' => false,
            'message' => empty($become_provider_settings) ? labels(NO_DATA_FOUND_IN_SETTING, 'No data found in setting') : labels(SETTINGS_RECEIVED_SUCCESSFULLY, 'Settings received successfully'),
            'data' => $become_provider_settings,
        ];
        return $this->response->setJSON($response);
    }

    // Private Helper methods
    private function fetch_country_codes()
    {
        $country_codes = fetch_details('country_codes', [], ['country_code']);

        return json_encode(array_column($country_codes, 'country_code'));
    }

    /**
     * Transform multilingual field data for API responses
     * 
     * This function handles both multi-language and single-language field formats:
     * - For multi-language fields: Returns default language in original field, requested language in translated_ field
     * - For single-language fields: Returns same content in both original and translated_ fields
     * 
     * @param array $fieldData The field data from settings (e.g., from get_settings)
     * @param string $fieldName The name of the field (e.g., 'about_us', 'privacy_policy')
     * @param string|null $requestedLanguage Optional requested language code (auto-detected if null)
     * @return array Transformed field data with original and translated_ versions
     */
    private function transformMultilingualField(array $fieldData, string $fieldName, ?string $requestedLanguage = null): array
    {
        // Get default language
        $defaultLanguage = get_default_language();

        // Requested language
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }


        // Initialize result
        $result = [];

        // If field missing
        if (!isset($fieldData[$fieldName])) {
            $result[$fieldName] = '';
            $result['translated_' . $fieldName] = '';
            return $result;
        }

        $fieldValue = $fieldData[$fieldName];

        // Case A: Multi-language format (array with language codes as keys)
        if (is_array($fieldValue) && $this->isMultilingualField($fieldValue)) {
            // Always populate default language even if that translation is missing
            $defaultContent = resolve_translation_fallback($fieldValue, [$defaultLanguage, 'en']);

            // Requested language still tries requested -> default -> English -> first available
            $requestedContent = resolve_translation_fallback($fieldValue, [$requestedLanguage, $defaultLanguage, 'en']);

            $result[$fieldName] = $defaultContent;
            $result['translated_' . $fieldName] = $requestedContent;
        }
        // Case B: Nested multilingual format (e.g., {"about_us": {"en": "content", "hi": "content"}})
        // OR old format wrapped in nested structure (e.g., {"customer_privacy_policy": {"customer_privacy_policy": "content"}})
        elseif (is_array($fieldValue) && isset($fieldValue[$fieldName])) {
            $translations = $fieldValue[$fieldName];

            // Check if this is old format wrapped in nested structure (e.g., {"customer_privacy_policy": {"customer_privacy_policy": "content"}})
            if (isset($translations[$fieldName])) {
                $content = is_string($translations[$fieldName]) ? $translations[$fieldName] : '';

                // For old format, both original and translated get the same value
                $result[$fieldName] = $content;
                $result['translated_' . $fieldName] = $content;
            } elseif (is_string($translations)) {
                $content = $translations;

                // For old format, both original and translated get the same value
                $result[$fieldName] = $content;
                $result['translated_' . $fieldName] = $content;
            } else {
                // This is actual multilingual format
                $defaultContent = resolve_translation_fallback($translations, [$defaultLanguage, 'en']);
                $requestedContent = resolve_translation_fallback($translations, [$requestedLanguage, $defaultLanguage, 'en']);

                $result[$fieldName] = $defaultContent;
                $result['translated_' . $fieldName] = $requestedContent;
            }
        }
        // Case C: Single-language field (direct string or single value) - OLD FORMAT
        else {
            $content = is_string($fieldValue) ? $fieldValue : '';

            // For old format single-language fields, both original and translated get the same value
            $result[$fieldName] = $content;
            $result['translated_' . $fieldName] = $content;
        }

        // Extra precaution: Filter out effectively empty HTML content
        // If the content is effectively empty (only spaces, &nbsp;, empty tags), return empty string
        // This handles cases where old/wrong data was already saved in the database
        if (isset($result[$fieldName]) && is_string($result[$fieldName])) {
            if (html_is_effectively_empty($result[$fieldName])) {
                $result[$fieldName] = '';
            }
        }
        if (isset($result['translated_' . $fieldName]) && is_string($result['translated_' . $fieldName])) {
            if (html_is_effectively_empty($result['translated_' . $fieldName])) {
                $result['translated_' . $fieldName] = '';
            }
        }

        return $result;
    }

    /**
     * Transform general_settings multilingual fields for API responses
     * 
     * This function processes general_settings fields that contain multilingual data
     * and creates both original (default language) and translated_ (requested language) versions
     * 
     * @param array $generalSettings The general_settings array from database
     * @param string|null $requestedLanguage Optional requested language code (auto-detected if null)
     * @return array Transformed general_settings with original and translated_ fields
     */
    private function transformGeneralSettingsMultilingualFields(array $generalSettings, ?string $requestedLanguage = null): array
    {
        // Get default language from database
        $defaultLanguage = get_default_language();

        // Get requested language from request headers if not provided
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Define multilingual fields in general_settings that need transformation
        // Note: message_for_customer_application and message_for_provider_application are moved to app_settings
        // before this transformation, so they are handled in transformAppSettingsMultilingualFields()
        $multilingualFields = [
            'company_title',
            'copyright_details',
            'address',
            'short_description'
        ];

        // Process each multilingual field
        foreach ($multilingualFields as $fieldName) {
            if (isset($generalSettings[$fieldName])) {
                $fieldValue = $generalSettings[$fieldName];

                // Check if field contains multilingual data (is an array with language codes)
                if (is_array($fieldValue) && $this->isMultilingualField($fieldValue)) {
                    $defaultContent = resolve_translation_fallback($fieldValue, [$defaultLanguage, 'en']);
                    $requestedContent = resolve_translation_fallback($fieldValue, [$requestedLanguage, $defaultLanguage, 'en']);

                    // Transform: original field gets default language, translated_ field gets requested language
                    $generalSettings[$fieldName] = $defaultContent;
                    $generalSettings['translated_' . $fieldName] = $requestedContent;
                } else {
                    // Non-multilingual field: keep original value and duplicate for translated_ field
                    $content = is_string($fieldValue) ? $fieldValue : '';
                    $generalSettings[$fieldName] = $content;
                    $generalSettings['translated_' . $fieldName] = $content;
                }
            }
        }

        return $generalSettings;
    }

    /**
     * Transform app_settings multilingual fields
     * 
     * This function transforms multilingual fields in app_settings (like message_for_customer_application,
     * message_for_provider_application) to return them in the same format as web_settings:
     * - Original field contains default language content
     * - translated_ prefixed field contains requested language content
     * 
     * @param array $appSettings App settings array
     * @param string|null $requestedLanguage Requested language code (null = auto-detect from request)
     * @return array Transformed app settings array
     */
    private function transformAppSettingsMultilingualFields(array $appSettings, ?string $requestedLanguage = null): array
    {
        // Get default language from database
        $defaultLanguage = get_default_language();

        // Get requested language from request headers if not provided
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Define multilingual fields in app_settings that need transformation
        $multilingualFields = [
            'message_for_customer_application',
            'message_for_provider_application'
        ];

        // Process each multilingual field
        foreach ($multilingualFields as $fieldName) {
            if (isset($appSettings[$fieldName])) {
                $fieldValue = $appSettings[$fieldName];

                // Check if field contains multilingual data (is an array with language codes)
                if (is_array($fieldValue) && $this->isMultilingualField($fieldValue)) {
                    $defaultContent = resolve_translation_fallback($fieldValue, [$defaultLanguage, 'en']);
                    $requestedContent = resolve_translation_fallback($fieldValue, [$requestedLanguage, $defaultLanguage, 'en']);

                    // Transform: original field gets default language, translated_ field gets requested language
                    $appSettings[$fieldName] = $defaultContent;
                    $appSettings['translated_' . $fieldName] = $requestedContent;
                } else {
                    // Non-multilingual field: keep original value and duplicate for translated_ field
                    $content = is_string($fieldValue) ? $fieldValue : '';
                    $appSettings[$fieldName] = $content;
                    $appSettings['translated_' . $fieldName] = $content;
                }
            }
        }

        return $appSettings;
    }

    /**
     * Transform web_settings multilingual fields for API responses
     * 
     * This function processes web_settings fields that contain multilingual data
     * and creates both original (default language) and translated_ (requested language) versions
     * 
     * @param array $webSettings The web_settings array from database
     * @param string|null $requestedLanguage Optional requested language code (auto-detected if null)
     * @return array Transformed web_settings with original and translated_ fields
     */
    private function transformWebSettingsMultilingualFields(array $webSettings, ?string $requestedLanguage = null): array
    {
        // Get default language from database
        $defaultLanguage = get_default_language();

        // Get requested language from request headers if not provided
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Define multilingual fields in web_settings that need transformation
        $multilingualFields = [
            'web_title',
            'message_for_customer_web',
            'cookie_consent_title',
            'cookie_consent_description',
            // Footer fields
            'footer_description',
            // Step title fields
            'step_1_title',
            'step_2_title',
            'step_3_title',
            'step_4_title',
            // Step description fields
            'step_1_description',
            'step_2_description',
            'step_3_description',
            'step_4_description'
        ];

        // Process each multilingual field
        foreach ($multilingualFields as $fieldName) {
            if (isset($webSettings[$fieldName])) {
                $fieldValue = $webSettings[$fieldName];

                // Check if field contains multilingual data (is an array with language codes)
                if (is_array($fieldValue) && $this->isMultilingualField($fieldValue)) {
                    $defaultContent = resolve_translation_fallback($fieldValue, [$defaultLanguage, 'en']);
                    $requestedContent = resolve_translation_fallback($fieldValue, [$requestedLanguage, $defaultLanguage, 'en']);

                    // Transform: original field gets default language, translated_ field gets requested language
                    $webSettings[$fieldName] = $defaultContent;
                    $webSettings['translated_' . $fieldName] = $requestedContent;
                } else {
                    // Non-multilingual field or single string - keep as is and duplicate for translated_ version
                    $content = is_string($fieldValue) ? $fieldValue : '';
                    $webSettings[$fieldName] = $content;
                    $webSettings['translated_' . $fieldName] = $content;
                }
            }
        }

        return $webSettings;
    }

    /**
     * Check if a field value contains multilingual data
     * 
     * @return bool True if field contains multilingual data (array with language codes)
     */
    private function isMultilingualField($value): bool
    {
        if (!is_array($value) || empty($value)) {
            return false;
        }

        foreach ($value as $key => $val) {
            // must be associative
            if (!is_string($key)) {
                return false;
            }

            // translations should be simple values
            if (!is_scalar($val)) {
                return false;
            }

            // language code pattern: en, en-ca, en_US, pt-BR, zh-Hans
            if (!preg_match('/^[a-z]{2,3}([_-][a-z0-9]{2,8})?$/i', $key)) {
                return false; // one bad key = not multilingual
            }
        }

        return true;
    }

    private function getProfileImagePath($profile_image)
    {
        $default_image = base_url("public/uploads/profiles/default.png");
        if (empty($profile_image))
            return $default_image;
        $image_paths = [
            base_url("public/uploads/profiles/" . $profile_image),
            base_url('/public/uploads/users/partners/' . $profile_image),
            "public/uploads/profiles/" . $profile_image
        ];
        foreach ($image_paths as $path) {
            if (check_exists($path)) {
                return filter_var($profile_image, FILTER_VALIDATE_URL) ? base_url($profile_image) : $path;
            }
        }
        return $default_image;
    }

    private function getPartnerName($partner_id)
    {
        return fetch_details('users', ['id' => $partner_id], ['username'])[0]['username'] ?? 'N/A';
    }

    /**
     * Transform web landing page multilingual fields for API responses
     * 
     * This function processes all multilingual fields in web landing page settings
     * including nested objects like process_flow_data and categories.
     * 
     * Transformation rules:
     * 1. Default language value replaces the original field
     * 2. Requested language value is added as translated_ prefixed field
     * 3. Falls back to default language if requested translation is missing
     * 4. Applies recursively to nested objects
     * 5. Handles categories with translations from the translations table
     * 
     * @param array $webSettings The web_settings array from database
     * @param string|null $requestedLanguage Optional requested language code (auto-detected if null)
     * @return array Transformed web_settings with original and translated_ fields
     */
    private function transformWebLandingPageMultilingualFields(array $webSettings, ?string $requestedLanguage = null): array
    {
        // Get default and requested languages
        $defaultLanguage = get_default_language();
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Define all multilingual fields in web landing page settings
        $multilingualFields = [
            // Main landing page fields
            'landing_page_title',
            'category_section_title',
            'category_section_description',
            'rating_section_title',
            'rating_section_description',
            'process_flow_title',
            'process_flow_description',
            'faq_section_title',
            'faq_section_description',
            // Step-specific fields (will be handled in process_flow_data)
            'step_1_title',
            'step_1_description',
            'step_2_title',
            'step_2_description',
            'step_3_title',
            'step_3_description',
            'step_4_title',
            'step_4_description',
            'cookie_consent_title',
            'cookie_consent_description',
            'message_for_customer_web'
        ];

        // Transform top-level multilingual fields
        foreach ($multilingualFields as $fieldName) {
            if (isset($webSettings[$fieldName])) {
                $webSettings = $this->transformSingleMultilingualField($webSettings, $fieldName, $defaultLanguage, $requestedLanguage);
            }
        }

        // Transform nested process_flow_data
        if (isset($webSettings['process_flow_data']) && is_array($webSettings['process_flow_data'])) {
            $webSettings['process_flow_data'] = $this->transformProcessFlowData($webSettings['process_flow_data'], $defaultLanguage, $requestedLanguage);
        }

        // Transform categories with translations from the translations table
        if (isset($webSettings['categories']) && is_array($webSettings['categories'])) {
            $webSettings['categories'] = $this->transformCategoriesWithTranslations($webSettings['categories']);
        }

        return $webSettings;
    }

    /**
     * Transform a single multilingual field
     * 
     * @param array $data The data array containing the field
     * @param string $fieldName The field name to transform
     * @param string $defaultLanguage Default language code
     * @param string $requestedLanguage Requested language code
     * @return array Updated data array
     */
    private function transformSingleMultilingualField(array $data, string $fieldName, string $defaultLanguage, string $requestedLanguage): array
    {
        $fieldValue = $data[$fieldName];

        // Check if field contains multilingual data (is an array with language codes)
        if (is_array($fieldValue) && $this->isMultilingualField($fieldValue)) {
            $defaultContent = resolve_translation_fallback($fieldValue, [$defaultLanguage, 'en']);
            $requestedContent = resolve_translation_fallback($fieldValue, [$requestedLanguage, $defaultLanguage, 'en']);

            // Transform: original field gets default language, translated_ field gets requested language
            $data[$fieldName] = $defaultContent;
            $data['translated_' . $fieldName] = $requestedContent;
        } else {
            // Non-multilingual field: keep original value and duplicate for translated_ field
            $content = is_string($fieldValue) ? $fieldValue : '';
            $data[$fieldName] = $content;
            $data['translated_' . $fieldName] = $content;
        }

        return $data;
    }

    /**
     * Transform process_flow_data nested objects
     * 
     * @param array $processFlowData Array of process flow steps
     * @param string $defaultLanguage Default language code
     * @param string $requestedLanguage Requested language code
     * @return array Transformed process flow data
     */
    private function transformProcessFlowData(array $processFlowData, string $defaultLanguage, string $requestedLanguage): array
    {
        foreach ($processFlowData as &$step) {
            // Transform title field
            if (isset($step['title'])) {
                $step = $this->transformSingleMultilingualField($step, 'title', $defaultLanguage, $requestedLanguage);
            }

            // Transform description field
            if (isset($step['description'])) {
                $step = $this->transformSingleMultilingualField($step, 'description', $defaultLanguage, $requestedLanguage);
            }
        }

        return $processFlowData;
    }

    /**
     * Transform categories with translations from the translations table
     * 
     * @param array $categories Array of categories
     * @return array Categories with translated names
     */
    private function transformCategoriesWithTranslations(array $categories): array
    {
        try {
            foreach ($categories as &$category) {
                if (isset($category['id'])) {
                    // Use existing helper function to get translated category data
                    $translatedData = get_translated_category_data_for_api($category['id'], $category);

                    // Merge translated data with original category data
                    $category = array_merge($category, $translatedData);
                }
            }
            return $categories;
        } catch (\Exception $e) {
            log_message('error', 'Error transforming categories with translations: ' . $e->getMessage());
            return $categories; // Return original data if translation fails
        }
    }

    /**
     * Get translated subscription data for API responses
     * 
     * Fetches translated name and description from translations table
     * and provides both original and translated versions.
     * 
     * @param int $subscriptionId Subscription ID
     * @param array $subscriptionData Original subscription data for fallback
     * @param string|null $requestedLanguage Optional requested language code
     * @return array Array with translated_name and translated_description
     */
    private function getTranslatedSubscriptionData(int $subscriptionId, array $subscriptionData, ?string $requestedLanguage = null): array
    {
        try {
            // Get default and requested languages
            $defaultLanguage = get_default_language();
            if ($requestedLanguage === null) {
                $requestedLanguage = get_current_language_from_request();
            }

            $subscriptionTranslationModel = new \App\Models\TranslatedSubscriptionModel();

            // Get current language translation
            $currentTranslation = $subscriptionTranslationModel->getTranslation($subscriptionId, $requestedLanguage);

            // Get default language translation
            $defaultTranslation = $subscriptionTranslationModel->getTranslation($subscriptionId, $defaultLanguage);

            $result = [];

            // Set subscription translations
            if ($currentTranslation && $requestedLanguage !== $defaultLanguage) {
                $result['translated_name'] = $currentTranslation['name'];
                $result['translated_description'] = $currentTranslation['description'];
            } else {
                // Fallback to default language or main table
                $result['translated_name'] = $defaultTranslation['name'] ?? $subscriptionData['name'] ?? '';
                $result['translated_description'] = $defaultTranslation['description'] ?? $subscriptionData['description'] ?? '';
                $result['name'] = $defaultTranslation['name'] ?? $subscriptionData['name'] ?? '';
                $result['description'] = $defaultTranslation['description'] ?? $subscriptionData['description'] ?? '';
            }

            return $result;
        } catch (\Exception $e) {
            // Log error and return fallback data
            log_message('error', 'Translation processing failed for subscription ' . $subscriptionId . ': ' . $e->getMessage());
            return [
                'translated_name' => $subscriptionData['name'] ?? '',
                'translated_description' => $subscriptionData['description'] ?? ''
            ];
        }
    }

    /**
     * Get translated service title with fallback logic
     * 
     * Priority order:
     * 1. Requested language translation
     * 2. Default language translation  
     * 3. First available translation
     * 4. Original service title from main table
     * 
     * @param int $serviceId Service ID
     * @param string $originalTitle Original title from main table
     * @param string|null $requestedLanguage Requested language code (null = auto-detect)
     * @return array Array with 'title' and 'translated_title' keys
     */
    private function getTranslatedServiceTitle(int $serviceId, string $originalTitle, ?string $requestedLanguage = null): array
    {
        // Get language codes
        $defaultLanguage = get_default_language();
        if ($requestedLanguage == null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Initialize result with original title as fallback
        $result = [
            'title' => $originalTitle,
            'translated_title' => $originalTitle
        ];

        // log_message('debug', 'serviceId: ' . $serviceId);


        try {
            // Get all translations for this service
            $serviceTranslationModel = new TranslatedServiceDetails_model();
            $allTranslations = $serviceTranslationModel->getAllTranslationsForService($serviceId);

            // log_message('debug', 'allTranslations: ' . json_encode($allTranslations));
            if (empty($allTranslations)) {
                // No translations available, return original title
                return $result;
            }

            // Index translations by language code for easy lookup
            $translationsByLang = [];
            foreach ($allTranslations as $translation) {
                $translationsByLang[$translation['language_code']] = $translation;
            }

            // Get default language translation
            $defaultTranslation = $translationsByLang[$defaultLanguage] ?? null;
            $defaultTitle = $defaultTranslation['title'] ?? '';

            // Get requested language translation
            $requestedTranslation = $translationsByLang[$requestedLanguage] ?? null;
            $requestedTitle = $requestedTranslation['title'] ?? '';

            // Apply fallback logic for translated title
            if (!empty($requestedTitle)) {
                // Use requested language translation
                $result['translated_title'] = $requestedTitle;
            } elseif (!empty($defaultTitle)) {
                // Fall back to default language translation
                $result['translated_title'] = $defaultTitle;
            } else {
                // Fall back to first available translation
                $firstTranslation = reset($allTranslations);
                $result['translated_title'] = $firstTranslation['title'] ?? $originalTitle;
            }

            // log_message('debug', 'defaultTitle: ' . $defaultTitle);
            // log_message('debug', 'allTranslations: ' . json_encode($allTranslations));
            // log_message('debug', 'firstTranslation: ' . json_encode($firstTranslation));
            // log_message('debug', 'originalTitle: ' . $originalTitle);

            // Set original title (default language or first available)
            if (!empty($defaultTitle)) {
                $result['title'] = $defaultTitle;
            } elseif (!empty($allTranslations)) {
                $firstTranslation = reset($allTranslations);
                $result['title'] = $firstTranslation['title'] ?? $originalTitle;
            }
            // log_message('debug', 'result: ' . json_encode($result));
        } catch (\Exception $e) {
            // Log error but don't break the flow
            log_message('error', 'Error getting translated service title for service ' . $serviceId . ': ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get translated partner company name from translated_partner_details table
     * 
     * @param int $partnerId Partner ID
     * @param string $originalCompanyName Original company name for fallback
     * @param string|null $requestedLanguage Optional requested language code
     * @return string Translated company name
     */
    private function getTranslatedPartnerCompanyName(int $partnerId, string $originalCompanyName, ?string $requestedLanguage = null): string
    {
        try {
            $defaultLanguage = get_default_language();
            $requestedLanguage = $requestedLanguage ?? get_current_language_from_request();

            $db = \Config\Database::connect();
            $builder = $db->table('translated_partner_details');

            // Try to get translation for requested language first
            $translation = $builder
                ->where('partner_id', $partnerId)
                ->where('language_code', $requestedLanguage)
                ->get()
                ->getRowArray();

            if ($translation && !empty($translation['company_name'])) {
                return $translation['company_name'];
            }

            // If requested language not found, try default language
            if ($requestedLanguage !== $defaultLanguage) {
                $translation = $builder
                    ->where('partner_id', $partnerId)
                    ->where('language_code', $defaultLanguage)
                    ->get()
                    ->getRowArray();

                if ($translation && !empty($translation['company_name'])) {
                    return $translation['company_name'];
                }
            }

            // Fallback to original company name
            return $originalCompanyName;
        } catch (\Exception $e) {
            log_message('error', 'Failed to get translated partner company name: ' . $e->getMessage());
            return $originalCompanyName;
        }
    }

    /**
     * Get service ID by title and provider ID
     * 
     * @param string $title Service title
     * @param int $providerId Provider ID
     * @return int|null Service ID or null if not found
     */
    private function getServiceIdByTitleAndProviderId(string $title, int $providerId): ?int
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('services');

            $service = $builder
                ->where('title', $title)
                ->where('user_id', $providerId)
                ->get()
                ->getRowArray();

            return $service['id'] ?? null;
        } catch (\Exception $e) {
            log_message('error', 'Failed to get service ID by title and provider ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Transform become provider multilingual fields for API responses
     * 
     * This function processes all multilingual fields in become provider settings
     * including nested sections like hero_section, how_it_work_section, etc.
     * 
     * Transformation rules:
     * 1. Default language value replaces the original field
     * 2. Requested language value is added as translated_ prefixed field
     * 3. Falls back to default language if requested translation is missing
     * 4. Applies recursively to nested objects and arrays
     * 
     * @param array $becomeProviderSettings The become provider settings array from database
     * @param string|null $requestedLanguage Optional requested language code (auto-detected if null)
     * @return array Transformed settings with original and translated_ fields
     */
    private function transformBecomeProviderMultilingualFields(array $becomeProviderSettings, ?string $requestedLanguage = null): array
    {
        // Get default and requested languages
        $defaultLanguage = get_default_language();
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Define sections that contain multilingual fields
        $sectionsWithMultilingualFields = [
            'hero_section' => ['title', 'description', 'short_headline'],
            'how_it_work_section' => ['title', 'description', 'steps', 'short_headline'],
            'category_section' => ['title', 'description', 'short_headline'],
            'subscription_section' => ['title', 'description', 'short_headline'],
            'top_providers_section' => ['title', 'description', 'short_headline'],
            'review_section' => ['title', 'description', 'short_headline'],
            'faq_section' => ['title', 'description', 'faqs', 'short_headline'],
            'feature_section' => ['title', 'description', 'features', 'short_headline']
        ];

        // Transform each section
        foreach ($sectionsWithMultilingualFields as $sectionName => $multilingualFields) {
            if (isset($becomeProviderSettings[$sectionName])) {
                $becomeProviderSettings[$sectionName] = $this->transformBecomeProviderSection(
                    $becomeProviderSettings[$sectionName],
                    $multilingualFields,
                    $defaultLanguage,
                    $requestedLanguage
                );
            }
        }

        return $becomeProviderSettings;
    }

    /**
     * Transform a single become provider section with multilingual fields
     * 
     * @param array $section The section data
     * @param array $multilingualFields Array of field names that are multilingual
     * @param string $defaultLanguage Default language code
     * @param string $requestedLanguage Requested language code
     * @return array Transformed section
     */
    private function transformBecomeProviderSection(array $section, array $multilingualFields, string $defaultLanguage, string $requestedLanguage): array
    {
        foreach ($multilingualFields as $fieldName) {
            if (isset($section[$fieldName])) {
                // Handle special cases for nested arrays using switch statement
                switch ($fieldName) {
                    case 'steps':
                        if (is_array($section[$fieldName])) {
                            // Transform steps array (for how_it_work_section)
                            $transformedSteps = $this->transformBecomeProviderSteps($section[$fieldName], $defaultLanguage, $requestedLanguage);
                            // Merge the transformed steps back into the section
                            $section = array_merge($section, $transformedSteps);
                        }
                        break;

                    case 'faqs':
                        // Handle FAQ data - check if it's a string (double JSON encoded) or array
                        $faqsData = $section[$fieldName];
                        if (is_string($faqsData)) {
                            // Handle double JSON encoding - decode twice if needed
                            $faqsData = json_decode($faqsData, true);
                            if (is_string($faqsData)) {
                                $faqsData = json_decode($faqsData, true);
                            }
                        }

                        if (is_array($faqsData)) {
                            // Transform faqs array (for faq_section)
                            $transformedFaqs = $this->transformBecomeProviderFaqs($faqsData, $defaultLanguage, $requestedLanguage);
                            // Merge the transformed faqs back into the section
                            $section = array_merge($section, $transformedFaqs);
                        }
                        break;

                    case 'features':
                        if (is_array($section[$fieldName])) {
                            // Transform features array (for feature_section)
                            $section[$fieldName] = $this->transformBecomeProviderFeatures($section[$fieldName], $defaultLanguage, $requestedLanguage);
                        }
                        break;

                    default:
                        // Transform regular multilingual field
                        $section = $this->transformSingleMultilingualField($section, $fieldName, $defaultLanguage, $requestedLanguage);
                        break;
                }
            }
        }

        return $section;
    }

    /**
     * Transform steps array in how_it_work_section
     * 
     * The steps structure is organized by language codes:
     * {
     *   "en": [{"title": "...", "description": "..."}, ...],
     *   "hi": [{"title": "...", "description": "..."}, ...]
     * }
     * 
     * This transforms it to:
     * {
     *   "steps": [{"title": "default content", "description": "default content"}, ...],
     *   "translated_steps": [{"title": "requested content", "description": "requested content"}, ...]
     * }
     * 
     * @param array $steps Steps organized by language code
     * @param string $defaultLanguage Default language code
     * @param string $requestedLanguage Requested language code
     * @return array Transformed steps structure
     */
    private function transformBecomeProviderSteps(array $steps, string $defaultLanguage, string $requestedLanguage): array
    {
        // Get default language steps
        $defaultSteps = $steps[$defaultLanguage] ?? [];

        // Get requested language steps (fallback to default if not available)
        $requestedSteps = [];
        if (isset($steps[$requestedLanguage]) && !empty($steps[$requestedLanguage])) {
            $requestedSteps = $steps[$requestedLanguage];
        } else {
            $requestedSteps = $defaultSteps; // Fallback to default language
        }

        // Ensure we have the same number of steps for both languages
        // If requested language has fewer steps, pad with default language steps
        $maxSteps = max(count($defaultSteps), count($requestedSteps));

        for ($i = 0; $i < $maxSteps; $i++) {
            if (!isset($requestedSteps[$i]) || (empty($requestedSteps[$i]['title']) && empty($requestedSteps[$i]['description']))) {
                // If requested language step is missing or empty, use default language step
                if (isset($defaultSteps[$i])) {
                    $requestedSteps[$i] = $defaultSteps[$i];
                }
            }
        }

        return [
            'steps' => $defaultSteps,
            'translated_steps' => $requestedSteps
        ];
    }

    /**
     * Transform faqs array in faq_section
     * 
     * Each FAQ has question and answer objects organized by language codes:
     * [
     *   {
     *     "question": {"en": "What is eDemand?", "hi": ""},
     *     "answer": {"en": "eDemand is...", "hi": ""}
     *   }
     * ]
     * 
     * This transforms it to:
     * {
     *   "faqs": [{"question": "default content", "answer": "default content"}, ...],
     *   "translated_faqs": [{"question": "requested content", "answer": "requested content"}, ...]
     * }
     * 
     * @param array $faqs Array of FAQ objects with multilingual question/answer
     * @param string $defaultLanguage Default language code
     * @param string $requestedLanguage Requested language code
     * @return array Transformed faqs structure
     */
    private function transformBecomeProviderFaqs(array $faqs, string $defaultLanguage, string $requestedLanguage): array
    {
        $defaultFaqs = [];
        $translatedFaqs = [];

        foreach ($faqs as $faq) {
            // Check if this is old format (simple strings) or new format (multilingual arrays)
            $isOldFormat = isset($faq['question']) && isset($faq['answer']) &&
                !is_array($faq['question']) && !is_array($faq['answer']);

            if ($isOldFormat) {
                // OLD FORMAT: Send same values for both faqs and translated_faqs
                $question = $faq['question'] ?? '';
                $answer = $faq['answer'] ?? '';

                $defaultFaqs[] = [
                    'question' => $question,
                    'answer' => $answer
                ];

                $translatedFaqs[] = [
                    'question' => $question,
                    'answer' => $answer
                ];
            } else {
                // NEW FORMAT: Handle multilingual structure
                $defaultQuestion = '';
                $translatedQuestion = '';
                $defaultAnswer = '';
                $translatedAnswer = '';

                // Process question with fallback logic
                if (isset($faq['question'])) {
                    if (is_array($faq['question'])) {
                        // New format: multilingual array
                        $defaultQuestion = $faq['question'][$defaultLanguage] ?? '';

                        // If default language is empty, fallback to 'en' or first available
                        if (empty($defaultQuestion)) {
                            $defaultQuestion = $faq['question']['en'] ?? reset($faq['question']) ?: '';
                        }

                        // For translated question: requested language -> default language -> 'en' -> first available
                        $translatedQuestion = $faq['question'][$requestedLanguage] ?? '';
                        if (empty($translatedQuestion)) {
                            $translatedQuestion = $faq['question'][$defaultLanguage] ?? '';
                        }
                        if (empty($translatedQuestion)) {
                            $translatedQuestion = $faq['question']['en'] ?? '';
                        }
                        if (empty($translatedQuestion)) {
                            $translatedQuestion = reset($faq['question']) ?: '';
                        }
                    } else {
                        // Fallback to simple string (shouldn't happen in new format, but just in case)
                        $defaultQuestion = $faq['question'];
                        $translatedQuestion = $faq['question'];
                    }
                }

                // Process answer with fallback logic
                if (isset($faq['answer'])) {
                    if (is_array($faq['answer'])) {
                        // New format: multilingual array
                        $defaultAnswer = $faq['answer'][$defaultLanguage] ?? '';

                        // If default language is empty, fallback to 'en' or first available
                        if (empty($defaultAnswer)) {
                            $defaultAnswer = $faq['answer']['en'] ?? reset($faq['answer']) ?: '';
                        }

                        // For translated answer: requested language -> default language -> 'en' -> first available
                        $translatedAnswer = $faq['answer'][$requestedLanguage] ?? '';
                        if (empty($translatedAnswer)) {
                            $translatedAnswer = $faq['answer'][$defaultLanguage] ?? '';
                        }
                        if (empty($translatedAnswer)) {
                            $translatedAnswer = $faq['answer']['en'] ?? '';
                        }
                        if (empty($translatedAnswer)) {
                            $translatedAnswer = reset($faq['answer']) ?: '';
                        }
                    } else {
                        // Fallback to simple string (shouldn't happen in new format, but just in case)
                        $defaultAnswer = $faq['answer'];
                        $translatedAnswer = $faq['answer'];
                    }
                }

                // Build default FAQ (always use default language content)
                $defaultFaqs[] = [
                    'question' => $defaultQuestion,
                    'answer' => $defaultAnswer
                ];

                // Build translated FAQ (use requested language with fallbacks)
                $translatedFaqs[] = [
                    'question' => $translatedQuestion,
                    'answer' => $translatedAnswer
                ];
            }
        }

        return [
            'faqs' => $defaultFaqs,
            'translated_faqs' => $translatedFaqs
        ];
    }

    /**
     * Transform features array in feature_section
     * 
     * @param array $features Array of features
     * @param string $defaultLanguage Default language code
     * @param string $requestedLanguage Requested language code
     * @return array Transformed features
     */
    private function transformBecomeProviderFeatures(array $features, string $defaultLanguage, string $requestedLanguage): array
    {
        foreach ($features as &$feature) {
            // Transform title field
            if (isset($feature['title'])) {
                $feature = $this->transformSingleMultilingualField($feature, 'title', $defaultLanguage, $requestedLanguage);
            }

            // Transform description field
            if (isset($feature['description'])) {
                $feature = $this->transformSingleMultilingualField($feature, 'description', $defaultLanguage, $requestedLanguage);
            }

            if (isset($feature['short_headline'])) {
                $feature = $this->transformSingleMultilingualField($feature, 'short_headline', $defaultLanguage, $requestedLanguage);
            }
        }

        return $features;
    }

}