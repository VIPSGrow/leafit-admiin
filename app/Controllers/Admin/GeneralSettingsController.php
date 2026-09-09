<?php

namespace App\Controllers\Admin;

use App\Models\Settings;
use App\Models\Language_model;
use App\Services\utility\FileService;
use Config\Database;

class GeneralSettingsController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;
    private Language_model $languageModel;
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin = $this->session->get('email');
        $this->settingsModel = new Settings();
        $this->languageModel = new Language_model();
        $this->fileService = new FileService();
        helper('ResponceServices');
        helper('events');
        helper('function');
        helper('form');
    }

    public function main_system_setting_page()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('System Settings', 'System Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'main_system_settings');
        return view('backend/admin/template', $this->data);
    }

    public function general_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                    }
                }

                $updatedData = $this->request->getPost();
                $row = $this->settingsModel->where('variable', 'general_settings')->first();
                $data = json_decode($row['value'] ?? '{}', true) ?: [];

                $imageFields = [
                    'favicon' => ['folder' => 'site', 'old_file' => $data['favicon'] ?? null],
                    'half_logo' => ['folder' => 'site', 'old_file' => $data['half_logo'] ?? null],
                    'logo' => ['folder' => 'site', 'old_file' => $data['logo'] ?? null],
                    'partner_favicon' => ['folder' => 'site', 'old_file' => $data['partner_favicon'] ?? null],
                    'partner_half_logo' => ['folder' => 'site', 'old_file' => $data['partner_half_logo'] ?? null],
                    'partner_logo' => ['folder' => 'site', 'old_file' => $data['partner_logo'] ?? null],
                    'handyman_favicon' => ['folder' => 'site', 'old_file' => $data['handyman_favicon'] ?? null],
                    'handyman_half_logo' => ['folder' => 'site', 'old_file' => $data['handyman_half_logo'] ?? null],
                    'handyman_logo' => ['folder' => 'site', 'old_file' => $data['handyman_logo'] ?? null],
                    'login_image' => ['folder' => 'login_image', 'old_file' => $data['login_image'] ?? null],
                ];

                foreach ($imageFields as $key => $config) {
                    if (!empty($_FILES[$key]) && isset($_FILES[$key])) {
                        $file = $this->request->getFile($key);
                        if ($file && $file->isValid()) {
                            $result = $this->fileService->replace($config['old_file'], $file, $config['folder']);
                            if (!$result['error']) {
                                $updatedData[$key] = basename($result['path']);
                            } else {
                                return JsonError($result['message']);
                            }
                        } else {
                            $updatedData[$key] = $config['old_file'];
                        }
                    } else {
                        $updatedData[$key] = $config['old_file'];
                    }
                }

                unset($updatedData['update']);
                unset($updatedData[csrf_token()]);
                $updatedData['currency'] = (!empty($this->request->getPost('currency'))) ? $this->request->getPost('currency') : ($data['currency'] ?? '');
                $updatedData['country_currency_code'] = (!empty($this->request->getPost('country_currency_code'))) ? $this->request->getPost('country_currency_code') : ($data['country_currency_code'] ?? '');
                if ($this->request->getPost('decimal_point') == 0) {
                    $updatedData['decimal_point'] = "0";
                } elseif (!empty($this->request->getPost('decimal_point'))) {
                    $updatedData['decimal_point'] = $this->request->getPost('decimal_point');
                } else {
                    $updatedData['decimal_point'] = $data['decimal_point'];
                }
                if ($updatedData['distance_unit'] == 'miles') {
                    $distanceInMiles = $this->request->getPost('max_serviceable_distance');
                    $updatedData['distance_unit'] = $this->request->getPost('distance_unit');
                    $updatedData['max_serviceable_distance'] = round($distanceInMiles * 1.60934);
                }
                if (!empty($this->request->getPost('otp_system'))) {
                    $updatedData['otp_system'] = $this->request->getPost('otp_system');
                }

                $updatedData['company_title'] = $_POST['company_title'] ?? [];
                $updatedData['copyright_details'] = $_POST['copyright_details'] ?? [];
                $updatedData['address'] = $_POST['address'] ?? [];
                $updatedData['short_description'] = $_POST['short_description'] ?? [];

                $keys = ['customer_current_version_ios_app', 'customer_compulsary_update_force_update', 'provider_current_version_android_app', 'provider_current_version_ios_app', 'provider_compulsary_update_force_update', 'customer_app_maintenance_schedule_date', 'message_for_customer_application', 'customer_app_maintenance_mode', 'provider_app_maintenance_schedule_date', 'message_for_provider_application', 'provider_app_maintenance_mode', 'provider_location_in_provider_details', 'support_name', 'support_email', 'phone', 'system_timezone_gmt', 'system_timezone', 'primary_color', 'secondary_color', 'primary_shadow', 'booking_auto_cancle_duration', 'customer_playstore_url', 'customer_appstore_url', 'provider_playstore_url', 'provider_appstore_url', 'maxFilesOrImagesInOneMessage', 'maxFileSizeInMBCanBeSent', 'maxCharactersInATextMessage', 'android_google_interstitial_id', 'android_google_banner_id', 'ios_google_interstitial_id', 'ios_google_banner_id', "android_google_ads_status", "ios_google_ads_status", 'authentication_mode', 'company_map_location', 'support_hours', 'file_manager', 'aws_access_key_id', 'aws_secret_access_key', 'aws_default_region', 'aws_bucket', 'aws_url', 'schema_for_deeplink', 'enable_chat_image_upload', 'enable_chat_file_upload', 'max_serviceable_distance_type'];
                foreach ($keys as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $updatedData[$key] = $this->request->getPost($key);
                    } else {
                        $updatedData[$key] = $data[$key] ?? '';
                    }
                }

                $updatedData['customer_current_version_android_app'] = (!empty($this->request->getPost('customer_current_version_android_app'))) ? $this->request->getPost('customer_current_version_android_app') : ($data['customer_current_version_android_app'] ?? '');
                $updatedData['customer_current_version_ios_app'] = (!empty($this->request->getPost('customer_current_version_ios_app'))) ? $this->request->getPost('customer_current_version_ios_app') : ($data['customer_current_version_ios_app'] ?? '');
                $updatedData['provider_current_version_android_app'] = (!empty($this->request->getPost('provider_current_version_android_app'))) ? $this->request->getPost('provider_current_version_android_app') : ($data['provider_current_version_android_app'] ?? '');
                $updatedData['provider_current_version_ios_app'] = (!empty($this->request->getPost('provider_current_version_ios_app'))) ? $this->request->getPost('provider_current_version_ios_app') : ($data['provider_current_version_ios_app'] ?? '');
                $updatedData['customer_app_maintenance_schedule_date'] = (!empty($this->request->getPost('customer_app_maintenance_schedule_date'))) ? $this->request->getPost('customer_app_maintenance_schedule_date') : ($data['customer_app_maintenance_schedule_date'] ?? '');
                $updatedData['message_for_customer_application'] = (!empty($this->request->getPost('message_for_customer_application'))) ? $this->request->getPost('message_for_customer_application') : ($data['message_for_customer_application'] ?? '');
                $updatedData['provider_app_maintenance_schedule_date'] = (!empty($this->request->getPost('provider_app_maintenance_schedule_date'))) ? $this->request->getPost('provider_app_maintenance_schedule_date') : ($data['provider_app_maintenance_schedule_date'] ?? '');
                $updatedData['message_for_provider_application'] = (!empty($this->request->getPost('message_for_provider_application'))) ? $this->request->getPost('message_for_provider_application') : ($data['message_for_provider_application'] ?? '');

                $maintenanceInputTz = $this->request->getPost('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                if ($this->request->getPost('maintenance_schedule_entry_timezone')) {
                    $this->session->set('maintenance_schedule_entry_timezone', $this->request->getPost('maintenance_schedule_entry_timezone'));
                }
                if (array_key_exists('customer_app_maintenance_schedule_date', $_POST) && $updatedData['customer_app_maintenance_schedule_date'] !== '') {
                    $updatedData['customer_app_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['customer_app_maintenance_schedule_date'], $maintenanceInputTz);
                }
                if (array_key_exists('provider_app_maintenance_schedule_date', $_POST) && $updatedData['provider_app_maintenance_schedule_date'] !== '') {
                    $updatedData['provider_app_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['provider_app_maintenance_schedule_date'], $maintenanceInputTz);
                }

                $updatedData['customer_compulsary_update_force_update'] = $data['customer_compulsary_update_force_update'] ?? '0';
                $updatedData['provider_compulsary_update_force_update'] = $data['provider_compulsary_update_force_update'] ?? '0';
                $updatedData['provider_location_in_provider_details'] = $data['provider_location_in_provider_details'] ?? '0';
                $updatedData['provider_app_maintenance_mode'] = $data['provider_app_maintenance_mode'] ?? '0';
                $updatedData['customer_app_maintenance_mode'] = $data['customer_app_maintenance_mode'] ?? '0';
                $updatedData['android_google_ads_status'] = $data['android_google_ads_status'] ?? '0';
                $updatedData['ios_google_ads_status'] = $data['ios_google_ads_status'] ?? '0';
             
                if ($this->request->getPost('image_compression_preference') == 0) {
                    $updatedData['image_compression_preference'] = "0";
                    $updatedData['image_compression_quality'] = "0";
                } elseif (!empty($this->request->getPost('image_compression_preference'))) {
                    $updatedData['image_compression_preference'] = $this->request->getPost('image_compression_preference');
                } else {
                    $updatedData['image_compression_preference'] = $data['image_compression_preference'];
                }

                if (!empty($updatedData['system_timezone_gmt']) && $updatedData['system_timezone_gmt'] == " 00:00") {
                    $updatedData['system_timezone_gmt'] = '+' . trim($updatedData['system_timezone_gmt']);
                }

                if (isset($updatedData['aws_url'])) {
                    $updatedData['aws_url'] = rtrim($updatedData['aws_url'], '/');
                }

                if ($this->request->getPost('company_map_location')) {
                    $iframe = $this->request->getPost('company_map_location');
                    preg_match('/src=["\']([^"\']+)["\']/', $iframe, $matches);
                    $updatedData['company_map_location'] = !empty($matches[1]) ? $matches[1] : $iframe;
                }

                $file_transfer_process = $this->request->getPost('file_transfer_process') ?? 0;
                $updatedData['file_transfer_process'] = $file_transfer_process;

                $file_manager = $_POST['file_manager'];

                if ($file_transfer_process == 1) {
                    $queue = service('queue');
                    $queue->push('filemanagerchanges', 'fileManagerChangesJob', ['file_manager' => $file_manager]);
                }

                $this->settingsModel->upsert('storage_disk', $file_manager);
                $this->fileService->clearCache();

                $finalData = array_merge($data, $updatedData);
                $json_string = json_encode($finalData);

                if ($this->settingsModel->upsert('general_settings', $json_string)) {
                    return JsonSuccess(labels('Settings has been successfuly updated', 'Settings has been successfuly updated'));
                } else {
                    return JsonError(labels('Unable to update the settings', 'Unable to update the settings'));
                }
            }

            $row = $this->settingsModel->where('variable', 'general_settings')->first();
            if ($row) {
                $settings = json_decode($row['value'], true);

                $imageSettings = ['half_logo', 'partner_favicon', 'partner_half_logo', 'partner_logo', 'handyman_favicon', 'handyman_half_logo', 'handyman_logo', 'login_image', 'favicon', 'logo'];

                foreach ($imageSettings as $key) {
                    if (!array_key_exists($key, $settings)) {
                        continue;
                    }

                    if ($key === 'login_image') {
                        if (trim((string) $settings[$key]) === '') {
                            // Legacy uploads (via function_helper.php's upload_file()) always wrote a
                            // fixed "Login_BG.jpg" into public/frontend/retro/ without recording the
                            // filename in general_settings — fall back to that known name.
                            $settings[$key] = 'Login_BG.jpg';
                        }
                        // Same resolution as frontend/retro/template.php: FileService::url() already
                        // falls back to the legacy retro path when the file isn't in uploads/site/.
                        $settings[$key] = $this->fileService->url($settings[$key], 'login_image', 'public/frontend/retro/' . $settings[$key]);
                        continue;
                    }

                    $settings[$key] = $this->fileService->exists('site', $settings[$key])
                        ? $this->fileService->url($settings[$key], 'site')
                        : null;
                }

                if (!empty($settings)) {
                    $this->data = array_merge($this->data, $settings);
                }

                $multi_lang_company_fields = ['company_title', 'copyright_details', 'address', 'short_description'];
                foreach ($multi_lang_company_fields as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        continue;
                    } elseif (isset($settings[$field]) && is_string($settings[$field])) {
                        $defaultLangRows = $this->languageModel->select('code')->where('is_default', 1)->findAll();
                        $default_lang = !empty($defaultLangRows) ? $defaultLangRows[0]['code'] : 'en';
                        $this->data[$field] = [$default_lang => $settings[$field]];
                    }
                }
            }

            $settings['distance_unit'] = isset($settings['distance_unit']) ? $settings['distance_unit'] : 'km';
            if ($settings['distance_unit'] == 'miles') {
                $this->data['max_serviceable_distance'] = round($settings['max_serviceable_distance'] * 0.621371);
            }

            $this->data['timezones'] = get_timezone_array();
            $this->data['currencies'] = $this->getCurrencyList();

            $languages = $this->languageModel->select(['id', 'language', 'is_default', 'code'])->orderBy('id', 'ASC')->findAll();
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                $multiLangCompanyFields = ['company_title', 'copyright_details', 'address', 'short_description'];
                $this->data = $this->applyFallbacksToFields($this->data, $multiLangCompanyFields, $languages);
            }

            setPageInfo($this->data, labels('General Settings', 'General Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'general_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/GeneralSettingsController.php - general_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function get_approved_providers()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return JsonError(labels(ERROR_OCCURED, 'An error occurred'));
            }

            $partnersModel = new \App\Models\Partners_model();
            $rows = $partnersModel->getApprovedProviders();

            foreach ($rows as &$row) {
                $row['image'] = $this->fileService->url($row['image'], 'profiles');
            }
            unset($row);

            return JsonSuccess(labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'), $rows);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/GeneralSettingsController.php - get_approved_providers()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function save_provider_distances()
    {
        try {
            $distances = $this->request->getPost('distances');
            if (empty($distances) || !is_array($distances)) {
                return JsonError(labels('no_provider_distances_provided', 'No provider distances provided'));
            }

            $db = Database::connect();
            $db->transStart();

            foreach ($distances as $partnerId => $distance) {
                $partnerId = (int) $partnerId;
                $distance = ($distance !== '' && $distance !== null) ? (float) $distance : null;
                $db->table('partner_details')
                    ->where('partner_id', $partnerId)
                    ->update(['max_serviceable_distance' => $distance]);
            }

            $row = $this->settingsModel->where('variable', 'general_settings')->first();
            $data = json_decode($row['value'] ?? '{}', true) ?: [];
            $data['max_serviceable_distance_type'] = 'provider_wise';
            $this->settingsModel->upsert('general_settings', json_encode($data));

            $db->transComplete();
            if (!$db->transStatus()) {
                return JsonError(labels(ERROR_OCCURED, 'An error occurred'));
            }

            return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'));
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/GeneralSettingsController.php - save_provider_distances()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    private function getCurrencyList(): array
    {
        return [
            'AFN' => 'AFN - Afghanistan Afghani',
            'AED' => 'AED - United Arab Emirates Dirham',
            'ALL' => 'ALL - Albania Lek',
            'AMD' => 'AMD - Armenia Dram',
            'ANG' => 'ANG - Netherlands Antilles Guilder',
            'AOA' => 'AOA - Angola Kwanza',
            'ARS' => 'ARS - Argentina Peso',
            'AUD' => 'AUD - Australia Dollar',
            'AWG' => 'AWG - Aruba Guilder',
            'AZN' => 'AZN - Azerbaijan Manat',
            'BAM' => 'BAM - Bosnia and Herzegovina Convertible Mark',
            'BBD' => 'BBD - Barbados Dollar',
            'BDT' => 'BDT - Bangladesh Taka',
            'BGN' => 'BGN - Bulgaria Lev',
            'BHD' => 'BHD - Bahrain Dinar',
            'BIF' => 'BIF - Burundi Franc',
            'BMD' => 'BMD - Bermuda Dollar',
            'BND' => 'BND - Brunei Darussalam Dollar',
            'BOB' => 'BOB - Bolivia Bolíviano',
            'BRL' => 'BRL - Brazil Real',
            'BSD' => 'BSD - Bahamas Dollar',
            'BTN' => 'BTN - Bhutan Ngultrum',
            'BWP' => 'BWP - Botswana Pula',
            'BYN' => 'BYN - Belarus Ruble',
            'BZD' => 'BZD - Belize Dollar',
            'CAD' => 'CAD - Canada Dollar',
            'CDF' => 'CDF - Congo/Kinshasa Franc',
            'CHF' => 'CHF - Switzerland Franc',
            'CLP' => 'CLP - Chile Peso',
            'CNY' => 'CNY - China Yuan Renminbi',
            'COP' => 'COP - Colombia Peso',
            'CRC' => 'CRC - Costa Rica Colon',
            'CUC' => 'CUC - Cuba Convertible Peso',
            'CUP' => 'CUP - Cuba Peso',
            'CVE' => 'CVE - Cape Verde Escudo',
            'CZK' => 'CZK - Czech Republic Koruna',
            'DJF' => 'DJF - Djibouti Franc',
            'DKK' => 'DKK - Denmark Krone',
            'DOP' => 'DOP - Dominican Republic Peso',
            'DZD' => 'DZD - Algeria Dinar',
            'EGP' => 'EGP - Egypt Pound',
            'ERN' => 'ERN - Eritrea Nakfa',
            'ETB' => 'ETB - Ethiopia Birr',
            'EUR' => 'EUR - Euro Member Countries',
            'FJD' => 'FJD - Fiji Dollar',
            'FKP' => 'FKP - Falkland Islands (Malvinas) Pound',
            'GBP' => 'GBP - United Kingdom Pound',
            'GEL' => 'GEL - Georgia Lari',
            'GGP' => 'GGP - Guernsey Pound',
            'GHS' => 'GHS - Ghana Cedi',
            'GIP' => 'GIP - Gibraltar Pound',
            'GMD' => 'GMD - Gambia Dalasi',
            'GNF' => 'GNF - Guinea Franc',
            'GTQ' => 'GTQ - Guatemala Quetzal',
            'GYD' => 'GYD - Guyana Dollar',
            'HKD' => 'HKD - Hong Kong Dollar',
            'HNL' => 'HNL - Honduras Lempira',
            'HRK' => 'HRK - Croatia Kuna',
            'HTG' => 'HTG - Haiti Gourde',
            'HUF' => 'HUF - Hungary Forint',
            'IDR' => 'IDR - Indonesia Rupiah',
            'ILS' => 'ILS - Israel Shekel',
            'IMP' => 'IMP - Isle of Man Pound',
            'INR' => 'INR - India Rupee',
            'IQD' => 'IQD - Iraq Dinar',
            'IRR' => 'IRR - Iran Rial',
            'ISK' => 'ISK - Iceland Krona',
            'JEP' => 'JEP - Jersey Pound',
            'JMD' => 'JMD - Jamaica Dollar',
            'JOD' => 'JOD - Jordan Dinar',
            'JPY' => 'JPY - Japan Yen',
            'KES' => 'KES - Kenya Shilling',
            'KGS' => 'KGS - Kyrgyzstan Som',
            'KHR' => 'KHR - Cambodia Riel',
            'KMF' => 'KMF - Comorian Franc',
            'KPW' => 'KPW - Korea (North) Won',
            'KRW' => 'KRW - Korea (South) Won',
            'KWD' => 'KWD - Kuwait Dinar',
            'KYD' => 'KYD - Cayman Islands Dollar',
            'KZT' => 'KZT - Kazakhstan Tenge',
            'LAK' => 'LAK - Laos Kip',
            'LBP' => 'LBP - Lebanon Pound',
            'LKR' => 'LKR - Sri Lanka Rupee',
            'LRD' => 'LRD - Liberia Dollar',
            'LSL' => 'LSL - Lesotho Loti',
            'LYD' => 'LYD - Libya Dinar',
            'MAD' => 'MAD - Morocco Dirham',
            'MDL' => 'MDL - Moldova Leu',
            'MGA' => 'MGA - Madagascar Ariary',
            'MKD' => 'MKD - Macedonia Denar',
            'MMK' => 'MMK - Myanmar (Burma) Kyat',
            'MNT' => 'MNT - Mongolia Tughrik',
            'MOP' => 'MOP - Macau Pataca',
            'MRU' => 'MRU - Mauritania Ouguiya',
            'MUR' => 'MUR - Mauritius Rupee',
            'MVR' => 'MVR - Maldives (Maldive Islands) Rufiyaa',
            'MWK' => 'MWK - Malawi Kwacha',
            'MXN' => 'MXN - Mexico Peso',
            'MYR' => 'MYR - Malaysia Ringgit',
            'MZN' => 'MZN - Mozambique Metical',
            'NAD' => 'NAD - Namibia Dollar',
            'NGN' => 'NGN - Nigeria Naira',
            'NIO' => 'NIO - Nicaragua Cordoba',
            'NOK' => 'NOK - Norway Krone',
            'NPR' => 'NPR - Nepal Rupee',
            'NZD' => 'NZD - New Zealand Dollar',
            'OMR' => 'OMR - Oman Rial',
            'PAB' => 'PAB - Panama Balboa',
            'PEN' => 'PEN - Peru Sol',
            'PGK' => 'PGK - Papua New Guinea Kina',
            'PHP' => 'PHP - Philippines Peso',
            'PKR' => 'PKR - Pakistan Rupee',
            'PLN' => 'PLN - Poland Zloty',
            'PYG' => 'PYG - Paraguay Guarani',
            'QAR' => 'QAR - Qatar Riyal',
            'RON' => 'RON - Romania Leu',
            'RSD' => 'RSD - Serbia Dinar',
            'RUB' => 'RUB - Russia Ruble',
            'RWF' => 'RWF - Rwanda Franc',
            'SAR' => 'SAR - Saudi Arabia Riyal',
            'SBD' => 'SBD - Solomon Islands Dollar',
            'SCR' => 'SCR - Seychelles Rupee',
            'SDG' => 'SDG - Sudan Pound',
            'SEK' => 'SEK - Sweden Krona',
            'SGD' => 'SGD - Singapore Dollar',
            'SHP' => 'SHP - Saint Helena Pound',
            'SLL' => 'SLL - Sierra Leone Leone',
            'SOS' => 'SOS - Somalia Shilling',
            'SRD' => 'SRD - Suriname Dollar',
            'STN' => 'STN - São Tomé and Príncipe Dobra',
            'SVC' => 'SVC - El Salvador Colon',
            'SYP' => 'SYP - Syria Pound',
            'SZL' => 'SZL - eSwatini Lilangeni',
            'THB' => 'THB - Thailand Baht',
            'TJS' => 'TJS - Tajikistan Somoni',
            'TMT' => 'TMT - Turkmenistan Manat',
            'TND' => 'TND - Tunisia Dinar',
            'TOP' => "TOP - Tonga Pa'anga",
            'TRY' => 'TRY - Turkey Lira',
            'TTD' => 'TTD - Trinidad and Tobago Dollar',
            'TVD' => 'TVD - Tuvalu Dollar',
            'TWD' => 'TWD - Taiwan New Dollar',
            'TZS' => 'TZS - Tanzania Shilling',
            'UAH' => 'UAH - Ukraine Hryvnia',
            'UGX' => 'UGX - Uganda Shilling',
            'USD' => 'USD - United States Dollar',
            'UYU' => 'UYU - Uruguay Peso',
            'UZS' => 'UZS - Uzbekistan Som',
            'VEF' => 'VEF - Venezuela Bolívar',
            'VND' => 'VND - Viet Nam Dong',
            'VUV' => 'VUV - Vanuatu Vatu',
            'WST' => 'WST - Samoa Tala',
            'XAF' => 'XAF - Communauté Financière Africaine (BEAC) CFA Franc BEAC',
            'XCD' => 'XCD - East Caribbean Dollar',
            'XDR' => 'XDR - International Monetary Fund (IMF) Special Drawing Rights',
            'XOF' => 'XOF - Communauté Financière Africaine (BCEAO) Franc',
            'XPF' => 'XPF - Comptoirs Français du Pacifique (CFP) Franc',
            'YER' => 'YER - Yemen Rial',
            'ZAR' => 'ZAR - South Africa Rand',
            'ZMW' => 'ZMW - Zambia Kwacha',
            'ZWD' => 'ZWD - Zimbabwe Dollar',
        ];
    }

    private function applyFallbacksToFields(array $data, array $fields, array $languages): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = $this->ensureMultiLangFallbacks($data[$field], $languages);
            }
        }
        return $data;
    }

    private function ensureMultiLangFallbacks(array $translations, array $allLanguages): array
    {
        if (empty($translations))
            return [];

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
            $langCode = $lang['code'];
            if (empty($translations[$langCode]) && !empty($fallbackValue)) {
                $translations[$langCode] = $fallbackValue;
            }
        }

        return $translations;
    }
}
