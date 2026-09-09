<?php

namespace App\Controllers\Admin;

use App\Models\Settings;
use App\Models\Language_model;
use App\Services\utility\FileService;

class WebSettingsController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;
    private Language_model $languageModel;
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin    = $this->session->get('email');
        $this->settingsModel = new Settings();
        $this->languageModel = new Language_model();
        $this->fileService   = new FileService();
        helper('ResponceServices');
        helper('function');
    }

    public function web_setting_page()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $settings = get_settings('web_settings', true);
            if (!empty($settings)) {
                $multi_lang_fields = ['web_title', 'message_for_customer_web', 'cookie_consent_title', 'cookie_consent_description'];

                foreach ($multi_lang_fields as $field) {
                    if (isset($settings[$field])) {
                        if (is_array($settings[$field])) {
                            $this->data[$field] = $settings[$field];
                        } elseif (is_string($settings[$field])) {
                            $default_lang = 'en';
                            $langs = $this->languageModel->select('code')->where('is_default', 1)->findAll();
                            if (!empty($langs)) {
                                $default_lang = $langs[0]['code'];
                            }
                            $this->data[$field] = [$default_lang => $settings[$field]];
                        } else {
                            $this->data[$field] = [];
                        }
                    } else {
                        $this->data[$field] = [];
                    }
                }

                $this->data = array_merge($this->data, $settings);

                $maintenanceDisplayTz = $this->session->get('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                if (!empty($this->data['customer_web_maintenance_schedule_date'])) {
                    $this->data['customer_web_maintenance_schedule_date'] = maintenance_schedule_utc_to_local($this->data['customer_web_maintenance_schedule_date'], $maintenanceDisplayTz);
                }

                foreach ($multi_lang_fields as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        continue;
                    } elseif (isset($settings[$field]) && is_string($settings[$field])) {
                        $default_lang = 'en';
                        $langs = $this->languageModel->select('code')->where('is_default', 1)->findAll();
                        if (!empty($langs)) {
                            $default_lang = $langs[0]['code'];
                        }
                        $this->data[$field] = [$default_lang => $settings[$field]];
                    }
                }
            }

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                $multiLangWebFields = ['web_title', 'message_for_customer_web', 'cookie_consent_title', 'cookie_consent_description'];
                $this->data = $this->applyFallbacksToFields($this->data, $multiLangWebFields, $languages);
            }

            setPageInfo($this->data, labels('Web Settings', 'Web Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'web_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> WebSettingsController::web_setting_page()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function reset_theme_colors()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if (isset($result['error']) && $result['error']) {
            return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
        }

        $theme = $this->request->getPost('theme');
        if (!in_array($theme, ['light', 'dark'])) {
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }

        $seeder = new \App\Database\Seeds\WebThemeColorSeeder(new \Config\Database());

        if ($theme === 'light') {
            $seeder->resetLight();
            $defaults = \App\Database\Seeds\WebThemeColorSeeder::LIGHT_DEFAULTS;
        } else {
            $seeder->resetDark();
            $defaults = \App\Database\Seeds\WebThemeColorSeeder::DARK_DEFAULTS;
        }

        return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), $defaults);
    }

    public function web_setting_update()
    {
        try {
            $old_settings = get_settings('web_settings', true);

            if (isset($_POST['cookie_consent_status']) && $_POST['cookie_consent_status'] == 1) {
                $cookieConsentTitle       = $this->request->getPost('cookie_consent_title');
                $cookieConsentDescription = $this->request->getPost('cookie_consent_description');

                $validationErrors = [];
                if (!$this->hasAnyNonEmptyTranslationValue($cookieConsentTitle)) {
                    $validationErrors[] = labels('cookie_consent_title_is_required', 'Cookie consent title is required');
                }
                if (!$this->hasAnyNonEmptyTranslationValue($cookieConsentDescription)) {
                    $validationErrors[] = labels('cookie_consent_description_is_required', 'Cookie consent description is required');
                }
                if (!empty($validationErrors)) {
                    return JsonError(implode('<br>', $validationErrors));
                }
            }

            $updatedData = [];
            $updatedData['social_media'] = [];

            $updatedData['web_title'] = $_POST['web_title'] ?? [];
            $updatedData['playstore_url'] = $_POST['playstore_url'];
            $updatedData['customer_web_maintenance_mode'] = isset($_POST['customer_web_maintenance_mode']) ? 1 : 0;

            if (isset($_POST['customer_web_maintenance_mode']) && $_POST['customer_web_maintenance_mode'] == 1) {
                $maintenance_schedule_date = $this->request->getPost('customer_web_maintenance_schedule_date');

                if (empty($maintenance_schedule_date)) {
                    return JsonError(labels('maintenance_date_range_required', 'Please enter a valid start and end date for maintenance mode'));
                }

                $dateParts = explode(' to ', $maintenance_schedule_date);
                if (\count($dateParts) !== 2) {
                    return JsonError(labels('invalid_date_format', 'Please enter a valid date range'));
                }

                $startDate = \DateTime::createFromFormat('Y-m-d H:i', trim($dateParts[0]));
                $endDate   = \DateTime::createFromFormat('Y-m-d H:i', trim($dateParts[1]));

                if (!$startDate || !$endDate) {
                    return JsonError(labels('invalid_date_format', 'Please enter a valid date range'));
                }

                if ($startDate >= $endDate) {
                    return JsonError(labels('start_date_must_be_before_end_date', 'Start date must be before end date'));
                }

                $today = new \DateTime();
                if ($endDate < $today) {
                    return JsonError(labels('maintenance_end_date_must_be_today_or_later', 'Please enter a valid start and end date. End date must be today or later'));
                }
            }

            if ($this->isLoggedIn && $this->userIsAdmin) {
                if ($this->request->getPost('update')) {

                    $result = checkModificationInDemoMode($this->superadmin);
                    if (isset($result['error']) && $result['error']) {
                        return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                    }

                    $old_settings = get_settings('web_settings', true);
                    $old_data = ['landing_page_logo', 'landing_page_backgroud_image', 'rating_section_status', 'faq_section_status', 'category_section_status', 'category_ids', 'rating_ids', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image', 'process_flow_status', 'cookie_consent_status', 'app_section_status', 'register_provider_from_web_setting_status', 'partner_login_url', 'partner_register_url', 'light_primary_color', 'light_secondary_color', 'light_bg_color', 'light_text_color', 'light_card_bg_color', 'light_description_text_color', 'dark_primary_color', 'dark_secondary_color', 'dark_bg_color', 'dark_text_color', 'dark_card_bg_color', 'dark_description_text_color'];
                    foreach ($old_data as $key) {
                        $updatedData[$key] = (!empty($this->request->getPost($key))) ? $this->request->getPost($key) : (isset($old_settings[$key]) ? $old_settings[$key] : '');
                    }

                    $colorKeys = ['light_primary_color', 'light_secondary_color', 'light_bg_color', 'light_text_color', 'light_card_bg_color', 'light_description_text_color', 'dark_primary_color', 'dark_secondary_color', 'dark_bg_color', 'dark_text_color', 'dark_card_bg_color', 'dark_description_text_color'];
                    foreach ($colorKeys as $ck) {
                        if (!empty($updatedData[$ck]) && !preg_match('/^#[0-9a-fA-F]{6,8}$/', $updatedData[$ck])) {
                            $updatedData[$ck] = isset($old_settings[$ck]) ? $old_settings[$ck] : '';
                        }
                    }

                    $data = get_settings('web_settings', true);
                    $files_to_check = ['landing_page_logo', 'landing_page_backgroud_image', 'web_logo', 'web_favicon', 'web_half_logo', 'footer_logo', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image'];
                    foreach ($files_to_check as $row) {
                        $file = $this->request->getFile($row);
                        if ($file && $file->isValid()) {
                            if (valid_image($row)) {
                                $result = $this->fileService->upload($file, 'web_settings');
                                if (!$result['error']) {
                                    $updatedData[$row] = basename($result['path']);
                                } else {
                                    return JsonError($result['message']);
                                }
                            }
                        } else {
                            $updatedData[$row] = $data[$row] ?? '';
                        }
                    }

                    $updatedSocialMedia = [];
                    $request = \Config\Services::request();
                    foreach ($_POST['social_media'] as $i => $item) {
                        if ($item['exist_url'] == 'new') {
                            $file = $request->getFile("social_media.{$i}.file");
                            if ($file && $file->isValid()) {
                                $result = $this->fileService->upload($file, 'web_settings');
                                if (!$result['error']) {
                                    $updatedSocialMedia[] = ['url' => $item['url'], 'file' => basename($result['path'])];
                                } else {
                                    return JsonError($result['message']);
                                }
                            } else {
                                if (!empty($item['url'])) {
                                    $updatedSocialMedia[] = ['url' => $item['url'], 'file' => ''];
                                }
                            }
                        } else {
                            $updatedData1 = ['url' => ($item['exist_url'] != $item['url']) ? $item['url'] : $item['exist_url']];
                            $file = $request->getFile("social_media.{$i}.file");
                            if ($file && $file->isValid()) {
                                $result = $this->fileService->upload($file, 'web_settings');
                                if (!$result['error']) {
                                    $updatedData1['file'] = basename($result['path']);
                                } else {
                                    return JsonError($result['message']);
                                }
                            } else {
                                $updatedData1['file'] = $item['exist_file'];
                            }
                            $updatedSocialMedia[] = $updatedData1;
                        }
                    }
                    $updatedData['social_media'] = $updatedSocialMedia;

                    $updatedData['customer_web_maintenance_mode'] = ($this->request->getPost('customer_web_maintenance_mode') == 0) ? '0' : ((!empty($this->request->getPost('customer_web_maintenance_mode'))) ? $this->request->getPost('customer_web_maintenance_mode') : '0');
                    $updatedData['app_section_status']            = ($this->request->getPost('app_section_status') == 0) ? '0' : ((!empty($this->request->getPost('app_section_status'))) ? 1 : '0');
                    $updatedData['cookie_consent_status']         = isset($_POST['cookie_consent_status']) && $_POST['cookie_consent_status'] == '1' ? 1 : 0;

                    if ($updatedData['cookie_consent_status'] == 1) {
                        $updatedData['cookie_consent_title']       = $_POST['cookie_consent_title'] ?? [];
                        $updatedData['cookie_consent_description'] = $_POST['cookie_consent_description'] ?? [];
                    } else {
                        $updatedData['cookie_consent_title']       = isset($data['cookie_consent_title']) ? $data['cookie_consent_title'] : [];
                        $updatedData['cookie_consent_description'] = isset($data['cookie_consent_description']) ? $data['cookie_consent_description'] : [];
                    }

                    $updatedData['register_provider_from_web_setting_status'] = ($this->request->getPost('register_provider_from_web_setting_status') == 0) ? '0' : ((!empty($this->request->getPost('register_provider_from_web_setting_status'))) ? 1 : '0');
                    $updatedData['message_for_customer_web']                  = $_POST['message_for_customer_web'] ?? [];
                    $updatedData['customer_web_maintenance_schedule_date']    = (!empty($this->request->getPost('customer_web_maintenance_schedule_date'))) ? $this->request->getPost('customer_web_maintenance_schedule_date') : (isset($data['customer_web_maintenance_schedule_date']) ? $data['customer_web_maintenance_schedule_date'] : '');

                    $maintenanceInputTzWeb = $this->request->getPost('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                    if ($this->request->getPost('maintenance_schedule_entry_timezone')) {
                        $this->session->set('maintenance_schedule_entry_timezone', $this->request->getPost('maintenance_schedule_entry_timezone'));
                    }
                    if (array_key_exists('customer_web_maintenance_schedule_date', $_POST) && !empty(trim($updatedData['customer_web_maintenance_schedule_date'] ?? ''))) {
                        $updatedData['customer_web_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['customer_web_maintenance_schedule_date'], $maintenanceInputTzWeb);
                    }

                    $updatedData['applestore_url'] = $_POST['applestore_url'];

                    $oldCustomerWebMaintenanceMode = isset($old_settings['customer_web_maintenance_mode']) ? (string)$old_settings['customer_web_maintenance_mode'] : '0';
                    $newCustomerWebMaintenanceMode = isset($updatedData['customer_web_maintenance_mode']) ? (string)$updatedData['customer_web_maintenance_mode'] : '0';

                    foreach ($updatedData as $key => $val) {
                        $old_settings[$key] = $val;
                    }

                    unset($old_settings[csrf_token()]);
                    unset($old_settings['update']);
                    $json_string = json_encode($old_settings);

                    if ($this->settingsModel->upsert('web_settings', $json_string)) {
                        if ($oldCustomerWebMaintenanceMode !== '1' && $newCustomerWebMaintenanceMode === '1') {
                            try {
                                queue_notification_service(
                                    eventType: 'maintenance_mode',
                                    recipients: [],
                                    context: [],
                                    options: ['channels' => ['fcm', 'email', 'sms'], 'user_groups' => [2, 3], 'platforms' => ['web']]
                                );
                            } catch (\Throwable $notificationError) {
                                log_message('error', '[MAINTENANCE_MODE] Notification error trace (web): ' . $notificationError->getTraceAsString());
                            }
                        }
                        return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'));
                    }
                    return JsonError(labels(ERROR_OCCURED, 'An error occurred'));
                }

                // GET fallback
                $settings = get_settings('web_settings', true);
                if (!empty($settings)) {
                    $this->data = array_merge($this->data, $settings);
                }
                $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
                $this->data['languages'] = $languages;
                setPageInfo($this->data, labels('Web Settings', 'Web Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'web_settings');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> WebSettingsController::web_setting_update()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function web_landing_page_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $settings = get_settings('web_settings', true);
            $multi_lang_fields = [
                'landing_page_title', 'category_section_title', 'category_section_description',
                'rating_section_title', 'rating_section_description', 'faq_section_title',
                'faq_section_description', 'process_flow_title', 'process_flow_description',
                'footer_description', 'step_1_title', 'step_2_title', 'step_3_title', 'step_4_title',
                'step_1_description', 'step_2_description', 'step_3_description', 'step_4_description',
            ];

            if (!empty($settings)) {
                foreach ($multi_lang_fields as $field) {
                    if (isset($settings[$field])) {
                        if (is_array($settings[$field])) {
                            $this->data[$field] = $settings[$field];
                        } elseif (is_string($settings[$field])) {
                            $default_lang = 'en';
                            $langs = $this->languageModel->select('code')->where('is_default', 1)->findAll();
                            if (!empty($langs)) {
                                $default_lang = $langs[0]['code'];
                            }
                            $this->data[$field] = [$default_lang => $settings[$field]];
                        }
                    } else {
                        $this->data[$field] = [];
                    }
                }

                $this->data = array_merge($this->data, $settings);

                foreach ($multi_lang_fields as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        continue;
                    } elseif (isset($settings[$field]) && is_string($settings[$field])) {
                        $default_lang = 'en';
                        $langs = $this->languageModel->select('code')->where('is_default', 1)->findAll();
                        if (!empty($langs)) {
                            $default_lang = $langs[0]['code'];
                        }
                        $this->data[$field] = [$default_lang => $settings[$field]];
                    }
                }
            }

            $db = \Config\Database::connect();
            $builder = $db->table('services_ratings sr');
            $builder->select('sr.*,u.image as profile_image,u.username')
                ->join('users u', '(sr.user_id = u.id)')
                ->orderBy('id', 'DESC');
            $services_ratings = $builder->get()->getResultArray();
            foreach ($services_ratings as $key => $row) {
                $services_ratings[$key]['profile_image'] = base_url('public/uploads/profiles/' . $row['profile_image']);
            }
            $this->data['services_ratings'] = $services_ratings;
            $this->data['categories_name']  = get_categories_with_translated_names();

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                $this->data = $this->applyFallbacksToFields($this->data, $multi_lang_fields, $languages);
                if (isset($this->data['process_flow_data']) && is_array($this->data['process_flow_data'])) {
                    $this->data['process_flow_data'] = $this->applyFallbacksToNestedItems($this->data['process_flow_data'], $languages, ['title', 'description']);
                }
            }

            setPageInfo($this->data, labels('Web Landing Page Settings', 'Web Landing Page Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'web_landing_page');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> WebSettingsController::web_landing_page_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    /** @deprecated No active route — preserved for backward compat only */
    public function web_setting_landing_page_update_old()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                $_SESSION['toastMessage']    = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/sms-gateways')->withCookies();
            }

            if (isset($_POST['disable_landing_page_settings_status']) && $_POST['disable_landing_page_settings_status'] == 1) {
                $default_latitude  = $this->request->getPost('default_latitude');
                $default_longitude = $this->request->getPost('default_longitude');

                if (empty($default_latitude) || empty($default_longitude)) {
                    $_SESSION['toastMessage']    = labels('latitude_and_longitude_are_required', 'Latitude and longitude are required when disable landing page settings is enabled');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                $lat = (float)$default_latitude;
                if ($lat < -90 || $lat > 90) {
                    $_SESSION['toastMessage']    = labels('please_enter_valid_latitude', 'Please enter a valid latitude (between -90 and 90)');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                $lng = (float)$default_longitude;
                if ($lng < -180 || $lng > 180) {
                    $_SESSION['toastMessage']    = labels('please_enter_valid_longitude', 'Please enter a valid longitude (between -180 and 180)');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                if (!$this->validatePartnersInLocation($default_latitude, $default_longitude)) {
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }
            }

            $multi_lang_landing_fields = [
                'landing_page_title', 'category_section_title', 'category_section_description',
                'rating_section_title', 'rating_section_description', 'faq_section_title',
                'faq_section_description', 'process_flow_title', 'process_flow_description',
                'footer_description', 'step_1_title', 'step_2_title', 'step_3_title', 'step_4_title',
                'step_1_description', 'step_2_description', 'step_3_description', 'step_4_description',
            ];

            $updatedData = [];
            foreach ($multi_lang_landing_fields as $field) {
                $updatedData[$field] = $_POST[$field] ?? [];
            }

            $updatedData['disable_landing_page_settings_status'] = isset($_POST['disable_landing_page_settings_status']) ? 1 : 0;

            if ($updatedData['disable_landing_page_settings_status'] == 1) {
                $updatedData['rating_section_status']   = 0;
                $updatedData['faq_section_status']      = 0;
                $updatedData['category_section_status'] = 0;
                $updatedData['process_flow_status']     = 0;
            } else {
                $updatedData['rating_section_status']   = isset($_POST['rating_section_status']) ? 1 : 0;
                $updatedData['faq_section_status']      = isset($_POST['faq_section_status']) ? 1 : 0;
                $updatedData['category_section_status'] = isset($_POST['category_section_status']) ? 1 : 0;
                $updatedData['process_flow_status']     = isset($_POST['process_flow_status']) ? 1 : 0;
            }

            $default_latitude  = $this->request->getPost('default_latitude');
            $default_longitude = $this->request->getPost('default_longitude');
            $updatedData['default_latitude']  = !empty($default_latitude)  ? number_format((float)$default_latitude, 6, '.', '')  : '';
            $updatedData['default_longitude'] = !empty($default_longitude) ? number_format((float)$default_longitude, 6, '.', '') : '';

            $categories = $this->request->getPost('categories');
            $ratings    = $this->request->getPost('new_rating_ids');
            $updatedData['category_ids'] = !empty($categories) ? $categories : '';
            $updatedData['rating_ids']   = !empty($ratings)    ? $ratings    : '';

            $old_settings = get_settings('web_settings', true);
            $old_data = ['social_media', 'playstore_url', 'app_section_status', 'applestore_url', 'web_logo', 'web_favicon', 'web_half_logo', 'footer_logo'];
            foreach ($old_data as $key) {
                $updatedData[$key] = (!empty($this->request->getPost($key))) ? $this->request->getPost($key) : (isset($old_settings[$key]) ? $old_settings[$key] : '');
            }

            if ($this->isLoggedIn && $this->userIsAdmin) {
                if ($this->request->getPost('update')) {
                    $data = get_settings('web_settings', true);
                    $files_to_check = ['landing_page_logo', 'landing_page_backgroud_image', 'web_logo', 'web_favicon', 'web_half_logo', 'footer_logo', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image'];

                    foreach ($files_to_check as $row) {
                        $file = $this->request->getFile($row);
                        if ($file && $file->isValid()) {
                            if (valid_image($row)) {
                                $result = $this->fileService->upload($file, 'web_settings');
                                if (!$result['error']) {
                                    $updatedData[$row] = basename($result['path']);
                                } else {
                                    return JsonError($result['message']);
                                }
                            }
                        } else {
                            $updatedData[$row] = $data[$row] ?? '';
                        }
                    }

                    unset($updatedData[csrf_token()]);
                    unset($updatedData['update']);
                    $json_string = json_encode($updatedData);

                    if ($this->settingsModel->upsert('web_settings', $json_string)) {
                        $_SESSION['toastMessage']    = labels('Landing Page Settings has been successfully updated', 'Landing Page Settings has been successfully updated');
                        $_SESSION['toastMessageType'] = 'success';
                    } else {
                        $_SESSION['toastMessage']    = labels('Unable to update Landing Page', 'Unable to update Landing Page');
                        $_SESSION['toastMessageType'] = 'error';
                    }
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> WebSettingsController::web_setting_landing_page_update_old()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function web_setting_landing_page_update()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
            }

            $old_settings         = get_settings('web_settings', true);
            $landing_page_updates = [];

            $default_latitude  = $this->request->getPost('default_latitude');
            $default_longitude = $this->request->getPost('default_longitude');

            if (isset($_POST['disable_landing_page_settings_status']) && $_POST['disable_landing_page_settings_status'] == 'on') {
                if (empty($default_latitude) || empty($default_longitude)) {
                    return JsonError(labels('latitude_and_longitude_are_required', 'Latitude and longitude are required'));
                }

                $lat = (float)$default_latitude;
                if ($lat < -90 || $lat > 90) {
                    return JsonError(labels('please_enter_valid_latitude', 'Please enter a valid latitude (between -90 and 90)'));
                }

                $lng = (float)$default_longitude;
                if ($lng < -180 || $lng > 180) {
                    return JsonError(labels('please_enter_valid_longitude', 'Please enter a valid longitude (between -180 and 180)'));
                }

                if (!$this->validatePartnersInLocation($default_latitude, $default_longitude)) {
                    return JsonError(labels('no_providers_in_location', 'No providers are available in this location. Please use other latitude and longitude values.'));
                }
            }

            $multi_lang_fields = [
                'landing_page_title', 'category_section_title', 'category_section_description',
                'rating_section_title', 'rating_section_description', 'faq_section_title',
                'faq_section_description', 'process_flow_title', 'process_flow_description',
                'footer_description', 'step_1_title', 'step_2_title', 'step_3_title', 'step_4_title',
                'step_1_description', 'step_2_description', 'step_3_description', 'step_4_description',
            ];
            foreach ($multi_lang_fields as $field) {
                $landing_page_updates[$field] = $_POST[$field] ?? ($old_settings[$field] ?? []);
            }

            $landing_page_updates['disable_landing_page_settings_status'] = isset($_POST['disable_landing_page_settings_status']) ? 1 : 0;

            if ($landing_page_updates['disable_landing_page_settings_status'] == 1) {
                $landing_page_updates['rating_section_status']   = 0;
                $landing_page_updates['faq_section_status']      = 0;
                $landing_page_updates['category_section_status'] = 0;
                $landing_page_updates['process_flow_status']     = 0;
            } else {
                $landing_page_updates['rating_section_status']   = isset($_POST['rating_section_status']) ? 1 : 0;
                $landing_page_updates['faq_section_status']      = isset($_POST['faq_section_status']) ? 1 : 0;
                $landing_page_updates['category_section_status'] = isset($_POST['category_section_status']) ? 1 : 0;
                $landing_page_updates['process_flow_status']     = isset($_POST['process_flow_status']) ? 1 : 0;
            }

            $lat = $this->request->getPost('default_latitude');
            $lng = $this->request->getPost('default_longitude');
            $landing_page_updates['default_latitude']  = !empty($lat) ? number_format((float)$lat, 6, '.', '') : '';
            $landing_page_updates['default_longitude'] = !empty($lng) ? number_format((float)$lng, 6, '.', '') : '';

            $landing_page_updates['category_ids'] = $this->request->getPost('categories') ?? ($old_settings['category_ids'] ?? '');
            $landing_page_updates['rating_ids']   = $this->request->getPost('new_rating_ids') ?? ($old_settings['rating_ids'] ?? '');

            $files_to_check = ['landing_page_logo', 'landing_page_backgroud_image', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image'];
            foreach ($files_to_check as $row) {
                $file = $this->request->getFile($row);
                if ($file && $file->isValid()) {
                    if (valid_image($row)) {
                        $result = $this->fileService->upload($file, 'web_settings');
                        if (!$result['error']) {
                            $landing_page_updates[$row] = basename($result['path']);
                        } else {
                            return JsonError($result['message']);
                        }
                    }
                } else {
                    $landing_page_updates[$row] = $old_settings[$row] ?? '';
                }
            }

            foreach ($landing_page_updates as $key => $val) {
                $old_settings[$key] = $val;
            }
            unset($old_settings[csrf_token()]);
            unset($old_settings['update']);
            $json_string = json_encode($old_settings, JSON_UNESCAPED_UNICODE);

            if ($this->settingsModel->upsert('web_settings', $json_string)) {
                return JsonSuccess(labels('Landing Page Settings has been successfully updated', 'Landing Page Settings has been successfully updated'));
            }
            return JsonError(labels('Unable to update Landing Page', 'Unable to update Landing Page'));
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> WebSettingsController::web_setting_landing_page_update()');
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

    private function applyFallbacksToFields(array $data, array $fields, array $languages): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = $this->ensureMultiLangFallbacks($data[$field], $languages);
            }
        }
        return $data;
    }

    private function applyFallbacksToNestedItems(array $items, array $languages, array $fields): array
    {
        foreach ($items as $index => $item) {
            if (is_array($item)) {
                $items[$index] = $this->applyFallbacksToFields($item, $fields, $languages);
            }
        }
        return $items;
    }

    private function hasAnyNonEmptyTranslationValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $translation) {
                if ($this->isMeaningfulText($translation)) {
                    return true;
                }
            }
            return false;
        }
        return $this->isMeaningfulText($value);
    }

    private function isMeaningfulText($value): bool
    {
        if ($value === null) {
            return false;
        }
        $text = is_string($value) ? $value : (string) $value;
        if (function_exists('html_is_effectively_empty')) {
            return !html_is_effectively_empty($text);
        }
        return trim(strip_tags($text)) !== '';
    }

    private function validatePartnersInLocation($latitude, $longitude): bool
    {
        $settings = get_settings('general_settings', true);

        if (empty($settings['max_serviceable_distance'])) {
            return false;
        }

        $max_distance = $settings['max_serviceable_distance'];
        $db      = \Config\Database::connect();
        $builder = $db->table('partner_details pd');
        $builder->select("pd.partner_id, u.latitude, u.longitude, st_distance_sphere(POINT('$longitude', '$latitude'), POINT(u.longitude, u.latitude))/1000 as distance")
            ->join('users u', 'pd.partner_id = u.id')
            ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
            ->where('pd.is_approved', 1)
            ->where('ps.status', 'active')
            ->having('distance < ' . (float)$max_distance)
            ->groupBy('pd.partner_id');

        $partners = $builder->get()->getResultArray();
        return !empty($partners);
    }
}
