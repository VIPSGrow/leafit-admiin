<?php

namespace App\Controllers\Admin;

use App\Models\Settings;
use App\Models\Language_model;

class PolicyController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;
    private Language_model $languageModel;

    private const LEGAL_PAGE_TYPES = [
        'customer_terms_conditions' => [
            'label_key'      => 'customer_terms_and_conditions',
            'label_default'  => 'Customer Terms and Conditions',
            'event'          => 'terms_and_conditions_changed',
            'user_groups'    => [2, 3],
            'platforms'      => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'],
            'channels'       => ['fcm', 'email', 'sms'],
            'success_key'    => 'Terms & Conditions has been successfully updated',
            'error_key'      => 'Unable to update the terms & conditions',
        ],
        'terms_conditions' => [
            'label_key'      => 'partner_terms_and_conditions',
            'label_default'  => 'Partner Terms and Conditions',
            'event'          => 'terms_and_conditions_changed',
            'user_groups'    => [2, 3],
            'platforms'      => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'],
            'channels'       => ['fcm', 'email', 'sms'],
            'success_key'    => 'Terms & Conditions has been successfully updated',
            'error_key'      => 'Unable to update the terms & conditions',
        ],
        'handyman_terms_conditions' => [
            'label_key'      => 'handyman_terms_and_conditions',
            'label_default'  => 'Handyman Terms and Conditions',
            'event'          => 'terms_and_conditions_changed',
            'user_groups'    => [4],
            'platforms'      => ['android', 'ios', 'handyman_panel'],
            'channels'       => ['fcm'],
            'success_key'    => 'Terms & Conditions has been successfully updated',
            'error_key'      => 'Unable to update the terms & conditions',
        ],
        'customer_privacy_policy' => [
            'label_key'      => 'customer_privacy_policy',
            'label_default'  => 'Customer Privacy Policy',
            'event'          => 'privacy_policy_changed',
            'user_groups'    => [2, 3],
            'platforms'      => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'],
            'channels'       => ['fcm', 'email', 'sms'],
            'success_key'    => 'privacy Policy has been successfuly updated',
            'error_key'      => 'Unable to update the privacy policy',
        ],
        'privacy_policy' => [
            'label_key'      => 'partner_privacy_policy',
            'label_default'  => 'Partner Privacy Policy',
            'event'          => 'privacy_policy_changed',
            'user_groups'    => [2, 3],
            'platforms'      => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'],
            'channels'       => ['fcm', 'email', 'sms'],
            'success_key'    => 'privacy Policy has been successfuly updated',
            'error_key'      => 'Unable to update the privacy policy',
        ],
        'handyman_privacy_policy' => [
            'label_key'      => 'handyman_privacy_policy',
            'label_default'  => 'Handyman Privacy Policy',
            'event'          => 'privacy_policy_changed',
            'user_groups'    => [4],
            'platforms'      => ['android', 'ios', 'handyman_panel'],
            'channels'       => ['fcm'],
            'success_key'    => 'privacy Policy has been successfuly updated',
            'error_key'      => 'Unable to update the privacy policy',
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->superadmin    = $this->session->get('email');
        $this->settingsModel = new Settings();
        $this->languageModel = new Language_model();
        helper('ResponceServices');
        helper('function');
    }

    // -------------------------------------------------------------------------
    // Admin-panel policy pages (rich-text editors)
    // -------------------------------------------------------------------------

    public function legal_page(string $type)
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (!isset(self::LEGAL_PAGE_TYPES[$type])) {
                return redirect('admin/settings/system-settings');
            }
            $config = self::LEGAL_PAGE_TYPES[$type];

            if ($this->request->getMethod() === 'POST') {
                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                }

                $translations = $this->request->getPost($type) ?? [];
                foreach ($translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        $translations[$langCode] = null;
                    }
                }

                $json_string = json_encode([$type => $translations]);
                if (!$this->settingsModel->upsert($type, $json_string)) {
                    return JsonError(labels($config['error_key'], $config['error_key']));
                }

                try {
                    queue_notification_service(
                        eventType: $config['event'],
                        recipients: [],
                        context: [],
                        options: [
                            'channels'    => $config['channels'],
                            'user_groups' => $config['user_groups'],
                            'platforms'   => $config['platforms'],
                        ]
                    );
                } catch (\Throwable $notificationError) {
                    log_message('error', '[' . strtoupper($config['event']) . '] Notification error: ' . $notificationError->getTraceAsString());
                }

                $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
                return JsonSuccess(labels($config['success_key'], $config['success_key']), null, [
                    'content' => $this->ensureMultiLangFallbacks($translations, $languages),
                ]);
            }

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $row       = $this->settingsModel->where('variable', $type)->first();

            $content = [];
            if (!empty($row)) {
                $settings = json_decode($row['value'], true);
                if (isset($settings[$type]) && is_array($settings[$type])) {
                    $content = $this->ensureMultiLangFallbacks($settings[$type], $languages);
                } elseif (isset($settings[$type]) && is_string($settings[$type])) {
                    $defaultLang = $this->languageModel->select('code')->where('is_default', 1)->first()['code'] ?? 'en';
                    $content     = $this->ensureMultiLangFallbacks([$defaultLang => $settings[$type]], $languages);
                }
            }

            $pageTitle = labels($config['label_key'], $config['label_default']);

            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'error'           => false,
                    'legal_page_type' => $type,
                    'label'           => $pageTitle,
                    'title'           => $pageTitle . ' | ' . labels('admin_panel', 'Admin Panel'),
                    'content'         => $content,
                ]);
            }

            $this->data['legal_page_type']  = $type;
            $this->data['legal_page_types'] = self::LEGAL_PAGE_TYPES;
            $this->data['content']          = $content;
            $this->data['languages']        = $languages;
            setPageInfo($this->data, $pageTitle . ' | ' . labels('admin_panel', 'Admin Panel'), 'legal_pages');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> PolicyController::legal_page()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function about_us()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    $_SESSION['toastMessage']     = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }

                $about_us_translations = $_POST['about_us'] ?? [];
                foreach ($about_us_translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        $about_us_translations[$langCode] = null;
                    }
                }

                $json_string = json_encode(['about_us' => $about_us_translations]);
                if ($this->settingsModel->upsert('about_us', $json_string)) {
                    $_SESSION['toastMessage']     = labels('About-us section has been successfully updated', 'About-us section has been successfully updated');
                    $_SESSION['toastMessageType'] = 'success';
                } else {
                    $_SESSION['toastMessage']     = labels('Unable to update about-us section', 'Unable to update about-us section');
                    $_SESSION['toastMessageType'] = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/about-us')->withCookies();
            }

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $row       = $this->settingsModel->where('variable', 'about_us')->first();

            $this->data['about_us'] = [];
            if (!empty($row)) {
                $settings = json_decode($row['value'], true);
                if (isset($settings['about_us']) && is_array($settings['about_us'])) {
                    $this->data['about_us'] = $this->ensureMultiLangFallbacks($settings['about_us'], $languages);
                } elseif (isset($settings['about_us']) && is_string($settings['about_us'])) {
                    $defaultLang            = $this->languageModel->select('code')->where('is_default', 1)->first()['code'] ?? 'en';
                    $translations           = [$defaultLang => $settings['about_us']];
                    $this->data['about_us'] = $this->ensureMultiLangFallbacks($translations, $languages);
                }
            }

            $this->data['languages'] = $languages;
            setPageInfo($this->data, labels('About us Settings', 'About us Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'about_us');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> PolicyController::about_us()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function contact_us()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    $_SESSION['toastMessage']     = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }

                $contact_us_translations = $_POST['contact_us'] ?? [];
                $json_string             = json_encode(['contact_us' => $contact_us_translations]);

                if ($this->settingsModel->upsert('contact_us', $json_string)) {
                    $_SESSION['toastMessage']     = labels('Contact-us section has been successfully updated', 'Contact-us section has been successfully updated');
                    $_SESSION['toastMessageType'] = 'success';
                } else {
                    $_SESSION['toastMessage']     = labels('Unable to update contact-us section', 'Unable to update contact-us section');
                    $_SESSION['toastMessageType'] = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/contact-us')->withCookies();
            }

            $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $row       = $this->settingsModel->where('variable', 'contact_us')->first();

            $this->data['contact_us'] = [];
            if (!empty($row)) {
                $settings = json_decode($row['value'], true);
                if (isset($settings['contact_us']) && is_array($settings['contact_us'])) {
                    $this->data['contact_us'] = $this->ensureMultiLangFallbacks($settings['contact_us'], $languages);
                } elseif (isset($settings['contact_us']) && is_string($settings['contact_us'])) {
                    $defaultLang              = $this->languageModel->select('code')->where('is_default', 1)->first()['code'] ?? 'en';
                    $translations             = [$defaultLang => $settings['contact_us']];
                    $this->data['contact_us'] = $this->ensureMultiLangFallbacks($translations, $languages);
                }
            }

            $this->data['languages'] = $languages;
            setPageInfo($this->data, labels('Contact us Settings', 'Contact us Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'contact_us');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> PolicyController::contact_us()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    // -------------------------------------------------------------------------
    // Public-facing policy pages (no auth required)
    // -------------------------------------------------------------------------

    public function legal_page_preview(string $type)
    {
        if (!isset(self::LEGAL_PAGE_TYPES[$type])) {
            return redirect('/');
        }
        $config                     = self::LEGAL_PAGE_TYPES[$type];
        $settings                  = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $pageTitle                 = labels($config['label_key'], $config['label_default']) . ' | ' . $settings['company_title'];

        $this->data['title']            = labels($pageTitle, $pageTitle);
        $this->data['meta_description'] = $pageTitle;
        $this->data['content']          = $this->getTranslatedSetting($type, $type);
        $this->data['settings']         = $settings;
        return view('backend/admin/pages/legal_page_preview', $this->data);
    }

    public function about_us_page_preview()
    {
        $settings                  = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title']       = 'About Us | ' . $settings['company_title'];
        $this->data['meta_description'] = 'About Us | ' . $settings['company_title'];
        $this->data['about_us']    = $this->getTranslatedSetting('about_us', 'about_us');
        $this->data['settings']    = $settings;
        return view('backend/admin/pages/about_us_preview', $this->data);
    }

    public function contact_us_page_preview()
    {
        $settings                  = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title']       = 'Contact Us | ' . $settings['company_title'];
        $this->data['meta_description'] = 'Contact Us | ' . $settings['company_title'];
        $this->data['contact_us']  = $this->getTranslatedSetting('contact_us', 'contact_us');
        $this->data['settings']    = $settings;
        return view('backend/admin/pages/contact_us_preview', $this->data);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getTranslatedSetting(string $key, ?string $field = null): string
    {
        $session     = session();
        $currentLang = $session->get('lang') ?? 'en';

        $settings = get_settings($key, true);
        $value    = $field ? ($settings[$field] ?? '') : ($settings[$key] ?? '');

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $defaultLang = $this->languageModel->select('code')->where('is_default', 1)->first()['code'] ?? 'en';

            if (!empty($value[$currentLang])) {
                return $value[$currentLang];
            }
            if (!empty($value[$defaultLang])) {
                return $value[$defaultLang];
            }
            if (!empty($value)) {
                return reset($value);
            }
        }

        $oldValue = get_settings($key, true);
        if (is_string($oldValue)) {
            return $oldValue;
        }

        return '';
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
            $langCode = $lang['code'];
            if (empty($translations[$langCode]) && !empty($fallbackValue)) {
                $translations[$langCode] = $fallbackValue;
            }
        }

        return $translations;
    }
}
