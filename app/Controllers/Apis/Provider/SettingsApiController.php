<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\TranslatedSubscriptionModel;

class SettingsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
    protected $builder;
    protected $user_details = [];
    protected $excluded_routes =
        [
            "partner/api/v1/index",
            "partner/api/v1",
            "partner/api/v1/get_settings",
        ];

    protected $allowed_settings = ["general_settings", "terms_conditions", "privacy_policy", "handyman_terms_conditions", "handyman_privacy_policy", "about_us", "app_settings", "login_settings"];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
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
                if (isset($this->user_details['id'])) {
                    $setting_res['demo_mode'] = (ALLOW_MODIFICATION == 0) ? "1" : "0";

                    $setting_res['balance'] = fetch_details("users", ["id" => $this->user_details['id']], ['balance', 'payable_commision']);
                    $setting_res['balance'] = (isset($setting_res['balance'][0]['balance'])) ? $setting_res['balance'][0]['balance'] : "0";
                    $setting_res['payable_commision'] = fetch_details("users", ["id" => $this->user_details['id']], ['balance', 'payable_commision']);
                    $setting_res['payable_commision'] = (isset($setting_res['payable_commision'][0]['payable_commision'])) ? $setting_res['payable_commision'][0]['payable_commision'] : "0";
                    $partner_details = fetch_details('partner_details', ['partner_id' => $this->user_details['id']], 'is_accepting_custom_jobs');
                    $setting_res['is_accepting_custom_jobs'] = $partner_details[0]['is_accepting_custom_jobs'] ?? 0;
                }
                foreach ($setting as $type) {
                    $notallowed_settings = ["languages", "email_settings", "country_codes", "api_key_settings", "test",];
                    if (!in_array($type['variable'], $notallowed_settings)) {
                        $setting_res[$type['variable']] = get_settings($type['variable'], true);
                    }
                    $setting_res['general_settings']['at_store'] = isset($setting_res['general_settings']['at_store']) ? $setting_res['general_settings']['at_store'] : "1";
                    $setting_res['general_settings']['at_doorstep'] = isset($setting_res['general_settings']['at_doorstep']) ? $setting_res['general_settings']['at_doorstep'] : "1";
                }

                $setting_res['map_provider'] = get_map_provider() === 'openstreetmap' ? 'openstreetmap' : 'google';

                $general_settings = $setting_res['general_settings'];
                $general_settings['passport_verification_status'] = $general_settings['passport_verification_status'] ? $general_settings['passport_verification_status'] : "0";
                $general_settings['national_id_verification_status'] = $general_settings['national_id_verification_status'] ? $general_settings['national_id_verification_status'] : "0";
                $general_settings['address_id_verification_status'] = $general_settings['address_id_verification_status'] ? $general_settings['address_id_verification_status'] : "0";
                $general_settings['passport_required_status'] = $general_settings['passport_required_status'] ? $general_settings['passport_required_status'] : "0";
                $general_settings['national_id_required_status'] = $general_settings['national_id_required_status'] ? $general_settings['national_id_required_status'] : "0";
                $general_settings['address_id_required_status'] = $general_settings['address_id_required_status'] ? $general_settings['address_id_required_status'] : "0";

                $setting_res['general_settings'] = $general_settings;
                // Only include payment gateway settings if user is authenticated AND provider online payment is enabled
                if (!empty($this->user_details) && isset($this->user_details['id'])) {

                    // Ensure payment_gateways_settings exists, initialize as empty array if not set
                    if (!isset($setting_res['payment_gateways_settings']) || !is_array($setting_res['payment_gateways_settings'])) {
                        $setting_res['payment_gateways_settings'] = [];
                    }

                    $payment_gateway_settings = $setting_res['payment_gateways_settings'];

                    // Default provider_online_payment_setting to "on" (1) if not set in database
                    // This ensures backward compatibility and enables online payment by default
                    if (
                        !isset($payment_gateway_settings['provider_online_payment_setting']) ||
                        empty($payment_gateway_settings['provider_online_payment_setting'])
                    ) {
                        $payment_gateway_settings['provider_online_payment_setting'] = "1";
                    }

                    // Check if provider_online_payment_setting is enabled (value should be 1)
                    $provider_online_payment_enabled = isset($payment_gateway_settings['provider_online_payment_setting']) && $payment_gateway_settings['provider_online_payment_setting'] == 1;

                    if ($provider_online_payment_enabled) {
                        // Provider online payment is enabled - include payment gateway settings
                        // Remove sensitive keys that shouldn't be exposed to providers
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
                        // Provider online payment is disabled - remove payment gateway settings from response
                        unset($setting_res['payment_gateways_settings']);
                    }
                } else {
                    // User is not logged in - remove payment gateway settings from response
                    if (isset($setting_res['payment_gateways_settings'])) {
                        unset($setting_res['payment_gateways_settings']);
                    }
                }
            }

            // Maintenance schedule is stored in UTC in the database. All comparisons are done in UTC.
            $system_time_zone = isset($setting_res['general_settings']['system_timezone']) ? $setting_res['general_settings']['system_timezone'] : "Asia/Kolkata";
            date_default_timezone_set($system_time_zone);

            // Parse provider app maintenance schedule date range (stored as "start to end" in UTC).
            $provider_app_maintenance_mode_schedule_date = isset($setting_res['app_settings']['provider_app_maintenance_schedule_date'])
                ? explode("to", $setting_res['app_settings']['provider_app_maintenance_schedule_date'])
                : null;
            if (!empty($provider_app_maintenance_mode_schedule_date)) {
                $provider_app_maintenance_mode_start_date = isset($provider_app_maintenance_mode_schedule_date[0]) ? trim($provider_app_maintenance_mode_schedule_date[0]) : "";
                $provider_app_maintenance_mode_end_date = isset($provider_app_maintenance_mode_schedule_date[1]) ? trim($provider_app_maintenance_mode_schedule_date[1]) : "";
            } else {
                $provider_app_maintenance_mode_start_date = null;
                $provider_app_maintenance_mode_end_date = null;
            }

            // Store calculated values in temporary variables (added to app_settings after migration).
            $provider_app_scheduled_maintenance_mode = "0";
            $provider_app_maintenance_mode_start_datetime = null;
            $provider_app_maintenance_mode_end_datetime = null;

            if (!empty($provider_app_maintenance_mode_start_date) && !empty($provider_app_maintenance_mode_end_date)) {
                try {
                    // Stored schedule is already in UTC; parse as UTC for all comparisons and API output.
                    $utcTimeZoneObj = new \DateTimeZone('UTC');
                    $providerStartDateTimeUtc = new \DateTime($provider_app_maintenance_mode_start_date, $utcTimeZoneObj);
                    $providerEndDateTimeUtc = new \DateTime($provider_app_maintenance_mode_end_date, $utcTimeZoneObj);

                    $provider_app_maintenance_mode_start_datetime = $providerStartDateTimeUtc->format('Y-m-d H:i:s');
                    $provider_app_maintenance_mode_end_datetime = $providerEndDateTimeUtc->format('Y-m-d H:i:s');

                    // All comparisons in UTC: is maintenance scheduled in the future?
                    $nowUtc = new \DateTime('now', $utcTimeZoneObj);
                    if (
                        isset($setting_res['app_settings']['provider_app_maintenance_mode']) &&
                        $setting_res['app_settings']['provider_app_maintenance_mode'] == 1 &&
                        $providerStartDateTimeUtc > $nowUtc
                    ) {
                        $provider_app_scheduled_maintenance_mode = "1";
                    }
                } catch (\Exception $e) {
                    // In case of any parsing / timezone error, we keep the default values.
                }
            }

            // Decide if the provider app is currently in maintenance mode. All comparisons in UTC.
            if (isset($setting_res['app_settings']['provider_app_maintenance_mode']) && $setting_res['app_settings']['provider_app_maintenance_mode'] == 1) {
                try {
                    $utcTimeZoneObj = new \DateTimeZone('UTC');
                    $nowUtc = new \DateTime('now', $utcTimeZoneObj);
                    $startUtc = new \DateTime($provider_app_maintenance_mode_start_date, $utcTimeZoneObj);
                    $endUtc = new \DateTime($provider_app_maintenance_mode_end_date, $utcTimeZoneObj);
                    if (($nowUtc >= $startUtc) && ($nowUtc <= $endUtc)) {
                        $setting_res['app_settings']['provider_app_maintenance_mode'] = "1";
                    } else {
                        $setting_res['app_settings']['provider_app_maintenance_mode'] = "0";
                    }
                } catch (\Exception $e) {
                    $setting_res['app_settings']['provider_app_maintenance_mode'] = "0";
                }
            } else {
                $setting_res['app_settings']['provider_app_maintenance_mode'] = "0";
            }
            if (!empty($this->user_details['id'])) {
                $subscription = fetch_details('partner_subscriptions', ['partner_id' => $this->user_details['id']], [], 1, 0, 'id', 'DESC');
            }
            $subscription_information['subscription_id'] = isset($subscription[0]['subscription_id']) ? $subscription[0]['subscription_id'] : "";
            $subscription_information['isSubscriptionActive'] =
                (!empty($subscription[0]['status']) && $subscription[0]['status'] === 'active')
                ? 'active'
                : 'deactive';

            $subscription_information['created_at'] = isset($subscription[0]['created_at']) ? $subscription[0]['created_at'] : "";
            $subscription_information['updated_at'] = isset($subscription[0]['updated_at']) ? $subscription[0]['updated_at'] : "";
            $subscription_information['is_payment'] = isset($subscription[0]['is_payment']) ? $subscription[0]['is_payment'] : "";
            $subscription_information['id'] = isset($subscription[0]['id']) ? $subscription[0]['id'] : "";
            $subscription_information['partner_id'] = isset($subscription[0]['partner_id']) ? $subscription[0]['partner_id'] : "";
            $subscription_information['purchase_date'] = isset($subscription[0]['purchase_date']) ? $subscription[0]['purchase_date'] : "";
            $subscription_information['expiry_date'] = isset($subscription[0]['expiry_date']) ? $subscription[0]['expiry_date'] : "";
            $subscription_information['name'] = isset($subscription[0]['name']) ? $subscription[0]['name'] : "";
            $subscription_information['description'] = isset($subscription[0]['description']) ? $subscription[0]['description'] : "";
            $subscription_information['duration'] = isset($subscription[0]['duration']) ? $subscription[0]['duration'] : "";
            $subscription_information['price'] = isset($subscription[0]['price']) ? $subscription[0]['price'] : "";
            $subscription_information['discount_price'] = isset($subscription[0]['discount_price']) ? $subscription[0]['discount_price'] : "";
            $subscription_information['order_type'] = isset($subscription[0]['order_type']) ? $subscription[0]['order_type'] : "";
            $subscription_information['max_order_limit'] = isset($subscription[0]['max_order_limit']) ? $subscription[0]['max_order_limit'] : "";
            $subscription_information['is_commision'] = isset($subscription[0]['is_commision']) ? $subscription[0]['is_commision'] : "";
            $subscription_information['commission_threshold'] = isset($subscription[0]['commission_threshold']) ? $subscription[0]['commission_threshold'] : "";
            $subscription_information['commission_percentage'] = isset($subscription[0]['commission_percentage']) ? $subscription[0]['commission_percentage'] : "";
            $subscription_information['publish'] = isset($subscription[0]['publish']) ? $subscription[0]['publish'] : "";
            $subscription_information['tax_id'] = isset($subscription[0]['tax_id']) ? $subscription[0]['tax_id'] : "";
            $subscription_information['tax_type'] = isset($subscription[0]['tax_type']) ? $subscription[0]['tax_type'] : "";

            // Update subscription name and description to use translations table first
            // Since new subscriptions only store these fields in translations table
            // Use existing helper function to get current language from request header
            $currentLanguage = get_current_language_from_request();

            // Get default language from database
            $defaultLanguage = 'en';
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $defaultLanguage = $languages[0]['code'];
            }

            // Initialize subscription translation model
            $subscriptionTranslationModel = new TranslatedSubscriptionModel();

            // Get subscription translations if subscription exists
            if (!empty($subscription[0]['subscription_id'])) {
                $subscriptionId = $subscription[0]['subscription_id'];

                // PRIORITY LOGIC FOR NAME AND DESCRIPTION:
                // 1. First, try to get translation for the requested language
                // 2. If not found, try to get translation for the default language
                // 3. Only as final fallback, use main table data (for legacy subscriptions)

                // Get translations for requested language and default language
                $translation = $subscriptionTranslationModel->getTranslation($subscriptionId, $currentLanguage);
                if (!$translation && $currentLanguage !== $defaultLanguage) {
                    $translation = $subscriptionTranslationModel->getTranslation($subscriptionId, $defaultLanguage);
                }
                $defaultTranslation = $subscriptionTranslationModel->getTranslation($subscriptionId, $defaultLanguage);

                // Set main fields: use default language translations or fallback to main table
                $subscription_information['name'] = $defaultTranslation['name'] ?? $subscription_information['name'];
                $subscription_information['description'] = $defaultTranslation['description'] ?? $subscription_information['description'];

                // Set translated fields: use requested language, fallback to default language, then main table
                $subscription_information['translated_name'] = $translation['name'] ?? $defaultTranslation['name'] ?? $subscription_information['name'];
                $subscription_information['translated_description'] = $translation['description'] ?? $defaultTranslation['description'] ?? $subscription_information['description'];
            } else {
                // No subscription found, set translated fields to empty
                $subscription_information['translated_name'] = "";
                $subscription_information['translated_description'] = "";
            }

            if (!empty($subscription[0])) {
                $price = calculate_partner_subscription_price($subscription[0]['partner_id'], $subscription[0]['subscription_id'], $subscription[0]['id']);
            }
            $subscription_information['tax_value'] = isset($price[0]['tax_value']) ? $price[0]['tax_value'] : "";
            $subscription_information['price_with_tax'] = isset($price[0]['price_with_tax']) ? $price[0]['price_with_tax'] : "";
            $subscription_information['original_price_with_tax'] = isset($price[0]['original_price_with_tax']) ? $price[0]['original_price_with_tax'] : "";
            $subscription_information['tax_percentage'] = isset($price[0]['tax_percentage']) ? $price[0]['tax_percentage'] : "";
            $setting_res['subscription_information'] = json_decode(json_encode($subscription_information), true);
            if (!empty($setting_res['web_settings']['social_media'])) {
                foreach ($setting_res['web_settings']['social_media'] as &$row) {
                    $row['file'] = !empty($row['file']) ? service('fileService')->url($row['file'], 'web_settings') : "";
                }
            } else {
                $setting_res['web_settings']['social_media'] = [];
            }
            $keys_to_unset = [
                'refund_policy',
                'become_provider_page_settings',
                'sms_gateway_setting',
                'notification_settings',
                'firebase_settings',
                'country_codes_old'
            ];
            foreach ($keys_to_unset as $key) {
                if (array_key_exists($key, $setting_res)) {
                    unset($setting_res[$key]);
                }
            }
            // Mirror currency from general_settings into app_settings for mobile backward compatibility.
            foreach (['country_currency_code', 'currency', 'decimal_point'] as $currencyKey) {
                $setting_res['app_settings'][$currencyKey] = $setting_res['general_settings'][$currencyKey] ?? '';
            }

            // Add new provider maintenance scheduling fields to app_settings.
            // These fields are calculated earlier but added here after app_settings migration
            // to ensure they are not lost when app_settings is reset.
            $setting_res['app_settings']['provider_app_scheduled_maintenance_mode'] = $provider_app_scheduled_maintenance_mode;
            $setting_res['app_settings']['provider_app_maintenance_mode_start_datetime'] = $provider_app_maintenance_mode_start_datetime;
            $setting_res['app_settings']['provider_app_maintenance_mode_end_datetime'] = $provider_app_maintenance_mode_end_datetime;

            //for werb
            $setting_res['social_media'] = $setting_res['web_settings']['social_media'];
            $keys_to_unset = [
                'web_settings',
                'firebase_settings',
                'range_units',
                'country_code',
                'customer_privacy_policy',
                'customer_terms_conditions',
                'system_tax_settings',
            ];
            foreach ($keys_to_unset as $key) {
                if (array_key_exists($key, $setting_res)) {
                    unset($setting_res[$key]);
                }
            }
            $general_settings_keys_to_unset = [
                'customer_app_maintenance_schedule_date',
                'provider_app_maintenance_schedule_date',
                'favicon',
                'logo',
                'half_logo',
                'partner_favicon',
                'partner_logo',
                'partner_half_logo',
                'provider_location_in_provider_details',
                'system_timezone',
                'primary_color',
                'secondary_color',
                'primary_shadow',
                'max_serviceable_distance',
                'booking_auto_cancle_duration',
            ];
            foreach ($general_settings_keys_to_unset as $key) {
                unset($setting_res['general_settings'][$key]);
            }
            $app_setting = [
                'customer_current_version_android_app',
                'customer_current_version_ios_app',
                'customer_compulsary_update_force_update',
                'message_for_customer_application',
                'customer_app_maintenance_mode'
            ];
            foreach ($app_setting as $key) {
                unset($setting_res['app_settings'][$key]);
            }
            $setting_res['demo_mode'] = (ALLOW_MODIFICATION == 0) ? "1" : "0";

            $setting_res['available_country_codes'] = $this->fetch_country_codes();

            // Format translatable settings with language support
            // This adds translated_ prefixed fields for about_us, terms_conditions, privacy_policy etc.
            $multilingual_fields = ['about_us', 'terms_conditions', 'privacy_policy', 'contact_us', 'handyman_terms_conditions', 'handyman_privacy_policy'];

            foreach ($multilingual_fields as $field_name) {
                // Always transform, even if field missing entirely, so response
                // still returns the key with an empty string instead of omitting it.
                $transformed_field = $this->transformMultilingualField($setting_res, $field_name);
                $setting_res = array_merge($setting_res, $transformed_field);
            }

            // Transform general_settings multilingual fields
            // This handles fields like company_title, copyright_details, address, short_description etc.
            if (isset($setting_res['general_settings'])) {
                $setting_res['general_settings'] = $this->transformGeneralSettingsMultilingualFields($setting_res['general_settings']);
            }

            // Transform app_settings multilingual fields (maintenance messages etc.)
            if (isset($setting_res['app_settings'])) {
                $setting_res['app_settings'] = $this->transformAppSettingsMultilingualFields($setting_res['app_settings']);
            }

            if (isset($setting_res) && !empty($setting_res)) {
                $response = [
                    'error' => false,
                    'message' => labels(SETTING_RECIEVED_SUCCESSFULLY, "setting recieved Successfully"),
                    'data' => $setting_res
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND_IN_SETTING, "No data found in setting"),
                    'data' => $setting_res
                ];
            }
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_settings()');
            return $this->response->setJSON($response);
        }
    }


    //Private Helper Methods

    /**
     * Transform multilingual field data for API response
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
            $result[$fieldName] = [
                $fieldName => '',
                'translated_' . $fieldName => ''
            ];
            return $result;
        }

        $fieldValue = $fieldData[$fieldName];

        // Case A: Multi-language format
        if (is_array($fieldValue) && isset($fieldValue[$fieldName]) && is_array($fieldValue[$fieldName])) {
            $translations = $fieldValue[$fieldName];

            // Get default language content for main field
            $defaultContent = $translations[$defaultLanguage] ?? '';

            // Enhanced fallback logic for translated field:
            // 1. Try requested language
            // 2. Fall back to default language if requested not found
            // 3. Fall back to any available translation if both not found
            $requestedContent = '';
            if (isset($translations[$requestedLanguage]) && !empty($translations[$requestedLanguage])) {
                $requestedContent = $translations[$requestedLanguage];
            } elseif (!empty($defaultContent)) {
                $requestedContent = $defaultContent;
            } else {
                // Fallback to any available translation
                $requestedContent = !empty($translations) ? reset($translations) : '';
            }

            $result[$fieldName] = [
                $fieldName => $defaultContent,
                'translated_' . $fieldName => $requestedContent
            ];
        }
        // Case B: Single-language wrapped format
        elseif (is_array($fieldValue) && isset($fieldValue[$fieldName]) && is_string($fieldValue[$fieldName])) {
            $content = $fieldValue[$fieldName];

            $result[$fieldName] = [
                $fieldName => $content,
                'translated_' . $fieldName => $content
            ];
        }
        // Case C: Direct string
        else {
            $content = is_string($fieldValue) ? $fieldValue : '';

            $result[$fieldName] = [
                $fieldName => $content,
                'translated_' . $fieldName => $content
            ];
        }

        // Extra precaution: Filter out effectively empty HTML content
        // If the content is effectively empty (only spaces, &nbsp;, empty tags), return empty string
        // This handles cases where old/wrong data was already saved in the database
        if (isset($result[$fieldName][$fieldName]) && is_string($result[$fieldName][$fieldName])) {
            if (html_is_effectively_empty($result[$fieldName][$fieldName])) {
                $result[$fieldName][$fieldName] = '';
            }
        }
        if (isset($result[$fieldName]['translated_' . $fieldName]) && is_string($result[$fieldName]['translated_' . $fieldName])) {
            if (html_is_effectively_empty($result[$fieldName]['translated_' . $fieldName])) {
                $result[$fieldName]['translated_' . $fieldName] = '';
            }
        }

        return $result;
    }

    public function fetch_country_codes()
    {
        $country_codes = fetch_details('country_codes', [], ['country_code']);

        return json_encode(array_column($country_codes, 'country_code'));
    }

    private function transformGeneralSettingsMultilingualFields(array $generalSettings, ?string $requestedLanguage = null): array
    {
        // Get default language from database
        $defaultLanguage = get_default_language();

        // Get requested language from request headers if not provided
        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        // Define multilingual fields in general_settings that need transformation
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
                    // Get default language content
                    $defaultContent = $fieldValue[$defaultLanguage] ?? '';

                    // Get requested language content with fallback logic:
                    // 1. Try requested language
                    // 2. Fall back to default language if requested not found
                    // 3. Fall back to any available translation if both not found
                    $requestedContent = '';
                    if (isset($fieldValue[$requestedLanguage]) && !empty($fieldValue[$requestedLanguage])) {
                        $requestedContent = $fieldValue[$requestedLanguage];
                    } elseif (!empty($defaultContent)) {
                        $requestedContent = $defaultContent;
                    } else {
                        // Fallback to any available translation
                        $requestedContent = !empty($fieldValue) ? reset($fieldValue) : '';
                    }

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
     * Transform general_settings multilingual fields for API responses
     * 
     * This function processes general_settings fields that contain multilingual data
     * and creates both original (default language) and translated_ (requested language) versions
     * 
     * @param string|null $requestedLanguage Optional requested language code (auto-detected if null)
     * @return array Transformed general_settings with original and translated_ fields
     * 
     * Transform app_settings multilingual fields so that default language content
     * remains in the base field while the requested language lives in translated_*
     * keys. This mirrors the behavior implemented for customer-facing APIs.
     */
    private function transformAppSettingsMultilingualFields(array $appSettings, ?string $requestedLanguage = null): array
    {
        $defaultLanguage = get_default_language();

        if ($requestedLanguage === null) {
            $requestedLanguage = get_current_language_from_request();
        }

        $multilingualFields = [
            'message_for_customer_application',
            'message_for_provider_application',
        ];

        foreach ($multilingualFields as $fieldName) {
            if (!isset($appSettings[$fieldName])) {
                continue;
            }

            $fieldValue = $appSettings[$fieldName];

            if (is_array($fieldValue) && $this->isMultilingualField($fieldValue)) {
                $defaultContent = $fieldValue[$defaultLanguage] ?? '';

                $requestedContent = '';
                if (!empty($fieldValue[$requestedLanguage] ?? '')) {
                    $requestedContent = $fieldValue[$requestedLanguage];
                } elseif (!empty($defaultContent)) {
                    $requestedContent = $defaultContent;
                } elseif (!empty($fieldValue['en'] ?? '')) {
                    $requestedContent = $fieldValue['en'];
                } elseif (!empty($fieldValue)) {
                    $requestedContent = reset($fieldValue);
                }

                $appSettings[$fieldName] = $defaultContent;
                $appSettings['translated_' . $fieldName] = $requestedContent;
            } else {
                $content = is_string($fieldValue) ? $fieldValue : '';
                $appSettings[$fieldName] = $content;
                $appSettings['translated_' . $fieldName] = $content;
            }
        }

        return $appSettings;
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
}
