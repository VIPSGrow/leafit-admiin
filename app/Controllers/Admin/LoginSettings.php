<?php

namespace App\Controllers\Admin;

use App\Controllers\Admin\Admin;
use App\Models\Settings;
use CodeIgniter\HTTP\ResponseInterface;

class LoginSettings extends Admin
{
    protected $superadmin;
    private $db, $builder;
    protected $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin = $this->session->get('email');
        $this->db = \Config\Database::connect();
        $this->builder = $this->db->table('settings');
        $this->settingsModel = new Settings();
        helper('ResponceServices');
        helper('function');
    }

    /**
     * Display login settings page with existing values prefilled
     * Loads settings from database and passes to view
     * Uses fallback values from general_settings when login_settings doesn't exist
     */
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Load existing login settings from database
        $existingSettings = get_settings('login_settings', true);

        // Get authentication mode from existing settings or fallback general settings
        $authenticationMode = get_settings('general_settings', true)['authentication_mode'] ?? 'sms_gateway';

        // Determine OTP method
        $otpSmsStatus = ($authenticationMode === 'sms_gateway') ? 1 : 0;
        $otpFirebaseStatus = ($authenticationMode === 'firebase') ? 1 : 0;

        if (empty($existingSettings)) {

            // Fallback defaults (password requirements: length 8, all character rules on)
            $loginSettings = [
                'phone_authentication_enabled' => 1,
                'email_authentication_enabled' => 0,
                'social_authentication_enabled' => 0,
                'customer_password_login_enabled' => 1,
                'otp_sms_status' => $otpSmsStatus,
                'otp_firebase_status' => $otpFirebaseStatus,
                'authentication_mode' => $authenticationMode,
                'minimum_password_length' => '8',
                'require_at_least_one_uppercase' => 1,
                'require_at_least_one_lowercase' => 1,
                'require_at_least_one_number' => 1,
                'require_at_least_one_special_character' => 1,
            ];
        } else {

            // Start with existing settings
            $loginSettings = $existingSettings;

            // Override OTP-related values based on authentication_mode
            $loginSettings['otp_sms_status'] = $otpSmsStatus;
            $loginSettings['otp_firebase_status'] = $otpFirebaseStatus;

            // Ensure password requirement keys exist with defaults (all on, length 8)
            if (!isset($loginSettings['minimum_password_length'])) {
                $loginSettings['minimum_password_length'] = '8';
            }
            if (!isset($loginSettings['require_at_least_one_uppercase'])) {
                $loginSettings['require_at_least_one_uppercase'] = 1;
            }
            if (!isset($loginSettings['require_at_least_one_lowercase'])) {
                $loginSettings['require_at_least_one_lowercase'] = 1;
            }
            if (!isset($loginSettings['require_at_least_one_number'])) {
                $loginSettings['require_at_least_one_number'] = 1;
            }
            if (!isset($loginSettings['require_at_least_one_special_character'])) {
                $loginSettings['require_at_least_one_special_character'] = 1;
            }
        }

        // Pass settings to view
        $this->data['login_settings'] = $loginSettings;

        setPageInfo($this->data, labels('authentication_settings', 'Authentication Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'login_settings');
        return view('backend/admin/template', $this->data);
    }

    /**
     * Save login settings to database
     * Merges new data with existing data to preserve unchanged values
     */
    public function save()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Demo mode restriction
        if (
            $this->superadmin !== "superadmin@gmail.com" &&
            defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0
        ) {
            $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata(['toastMessage', 'toastMessageType']);
            return redirect()->to('admin/settings/authentication-settings')->withCookies();
        }

        try {
            /**
             * -------------------------------
             * LOGIN SETTINGS UPDATE
             * -------------------------------
             */
            $existingLoginSettings = get_settings('login_settings', true) ?: [];

            $otpSmsStatus = $this->request->getPost('otp_sms_status') === 'on' ? 1 : 0;

            $loginSettings = [
                'phone_authentication_enabled' =>
                    $this->request->getPost('phone_authentication_enabled') === 'on' ? 1 : 0,

                'email_authentication_enabled' =>
                    $this->request->getPost('email_authentication_enabled') === 'on' ? 1 : 0,

                'social_authentication_enabled' =>
                    $this->request->getPost('social_authentication_enabled') === 'on' ? 1 : 0,

                'customer_password_login_enabled' =>
                    $this->request->getPost('customer_password_login_enabled') === 'on' ? 1 : 0,

                // Password requirements: stored in login_settings JSON (minimum_password_length as string)
                'minimum_password_length' => (string) ($this->request->getPost('minimum_password_length') ?? '8'),
                'require_at_least_one_uppercase' => ($this->request->getPost('require_at_least_one_uppercase') === 'on' || $this->request->getPost('require_at_least_one_uppercase') === '1') ? 1 : 0,
                'require_at_least_one_lowercase' => ($this->request->getPost('require_at_least_one_lowercase') === 'on' || $this->request->getPost('require_at_least_one_lowercase') === '1') ? 1 : 0,
                'require_at_least_one_number' => ($this->request->getPost('require_at_least_one_number') === 'on' || $this->request->getPost('require_at_least_one_number') === '1') ? 1 : 0,
                'require_at_least_one_special_character' => ($this->request->getPost('require_at_least_one_special_character') === 'on' || $this->request->getPost('require_at_least_one_special_character') === '1') ? 1 : 0,
            ];

            // When minimum length is 0, no character restrictions are allowed (JS disables switches)
            $minLen = (int) $loginSettings['minimum_password_length'];
            if ($minLen === 0) {
                $loginSettings['require_at_least_one_uppercase'] = 0;
                $loginSettings['require_at_least_one_lowercase'] = 0;
                $loginSettings['require_at_least_one_number'] = 0;
                $loginSettings['require_at_least_one_special_character'] = 0;
            }

            // At least one of phone or email login must be enabled so providers can log in
            if ($loginSettings['phone_authentication_enabled'] != 1 && $loginSettings['email_authentication_enabled'] != 1) {
                $_SESSION['toastMessage'] = labels(
                    'at_least_one_login_method_required',
                    'At least one of Phone Login or Email Login must be enabled so providers can sign in.'
                );
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata(['toastMessage', 'toastMessageType']);
                return redirect()->to('admin/settings/authentication-settings')->withCookies();
            }

            $mergedLoginSettings = array_merge($existingLoginSettings, $loginSettings);

            if ($loginSettings['email_authentication_enabled'] == 1) {
                $smtp = get_settings('email_settings', true);
                $smtp = is_array($smtp) ? $smtp : json_decode($smtp, true);

                $required = [
                    'smtpHost',
                    'smtpUsername',
                    'smtpPort'
                ];

                foreach ($required as $field) {
                    if (empty($smtp[$field])) {
                        $_SESSION['toastMessage'] = labels(
                            'email_settings_missing',
                            'SMTP Settings not configured. Please configure Email Settings first.'
                        );
                        $_SESSION['toastMessageType'] = 'error';
                        $this->session->markAsFlashdata(['toastMessage', 'toastMessageType']);
                        return redirect()->to('admin/settings/authentication-settings')->withCookies();
                    }
                }
            }

            /**
             * -------------------------------
             * GENERAL SETTINGS UPDATE
             * -------------------------------
             */
            $authenticationMode = $otpSmsStatus === 1 ? 'sms_gateway' : 'firebase';

            $existingGeneralSettings = get_settings('general_settings', true) ?: [];

            $mergedGeneralSettings = array_merge(
                $existingGeneralSettings,
                ['authentication_mode' => $authenticationMode]
            );

            /**
             * -------------------------------
             * SAVE BOTH
             * -------------------------------
             */
            $loginSaved = $this->update_setting(
                'login_settings',
                json_encode($mergedLoginSettings)
            );

            $generalSaved = $this->update_setting(
                'general_settings',
                json_encode($mergedGeneralSettings)
            );

            if ($loginSaved && $generalSaved) {
                $_SESSION['toastMessage'] = labels(
                    'settings_saved_successfully',
                    'Settings saved successfully'
                );
                $_SESSION['toastMessageType'] = 'success';
            } else {
                $_SESSION['toastMessage'] = labels(
                    'Unable to save settings',
                    'Unable to save settings'
                );
                $_SESSION['toastMessageType'] = 'error';
            }

            $this->session->markAsFlashdata(['toastMessage', 'toastMessageType']);
            return redirect()->to('admin/settings/authentication-settings')->withCookies();
        } catch (\Throwable $th) {
            log_the_responce(
                $th,
                date("Y-m-d H:i:s") . ' --> LoginSettings::save()'
            );

            $_SESSION['toastMessage'] = labels(
                'something_went_wrong',
                'Something went wrong'
            );
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata(['toastMessage', 'toastMessageType']);

            return redirect()->to('admin/settings/authentication-settings')->withCookies();
        }
    }

    /**
     * Update or insert setting in database
     * Similar to Settings controller's update_setting method
     */
    private function update_setting($variable, $value)
    {
        try {
            $this->builder->where('variable', $variable);
            if (exists(['variable' => $variable], 'settings')) {
                $this->db->transStart();
                $this->builder->update(['value' => $value]);
                $this->db->transComplete();
            } else {
                $this->db->transStart();
                $this->builder->insert(['variable' => $variable, 'value' => $value]);
                $this->db->transComplete();
            }
            return $this->db->transStatus();
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/LoginSettings.php - update_setting()');
            return false;
        }
    }
}
