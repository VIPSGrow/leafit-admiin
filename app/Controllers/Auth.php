<?php

namespace App\Controllers;

use App\Models\Partners_model;
use App\Models\Users_model;
use App\Services\utility\SlugService;
use PDO;

/**
 * Class Auth
 *
 * @property Ion_auth|Ion_auth_model $ion_auth      The ION Auth spark
 * @package  CodeIgniter-Ion-Auth
 * @author   Ben Edmunds <ben.edmunds@gmail.com>
 * @author   Benoit VRIGNAUD <benoit.vrignaud@zaclys.net>
 * @license  https://opensource.org/licenses/MIT	MIT License
 */
class Auth extends BaseController
{
    /**
     *
     * @var array
     */
    protected bool $is_loggedin = false;

    public $data = [];
    /**
     * Configuration
     *
     * @var \IonAuth\Config\IonAuth
     */
    protected $configIonAuth;
    /**
     * Session
     *
     * @var \CodeIgniter\Session\Session
     */
    protected $session;
    /**
     * Validation library
     *
     * @var \CodeIgniter\Validation\Validation
     */
    protected $validation;
    /**
     * Validation list template.
     *
     * @var string
     * @see https://bcit-ci.github.io/CodeIgniter4/libraries/validation.html#configuration
     */
    protected $validationListTemplate = 'list';
    /**
     * Views folder
     * Set it to 'auth' if your views files are in the standard application/Views/auth
     *
     * @var string
     */
    protected $viewsFolder = 'frontend/retro';
    /**
     * Constructor
     *
     * @return void
     */
    public $partner_model;
    protected SlugService $slugService;
    public function __construct()
    {
        $this->data['admin'] = $this->userIsAdmin;
        $this->validation = \Config\Services::validation();
        helper(['form', 'url']);
        $this->configIonAuth = config('IonAuth');
        $this->session = \Config\Services::session();
        $this->partner_model = new Partners_model();
        $this->slugService = new SlugService();
        if (!empty($this->configIonAuth->templates['errors']['list'])) {
            $this->validationListTemplate = $this->configIonAuth->templates['errors']['list'];
        }
    }
    /**
     * Redirect if needed, otherwise display the user list
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function index()
    {
        if (!$this->ionAuth->loggedIn()) {
            // redirect them to the login page
            return redirect()->to('/partner/login');
        } else {
            if ($this->ionAuth->isAdmin()) {
                return redirect()->to('/admin/dashboard');
            } else {
                return redirect()->to('/partner/dashboard');
            }
        }
    }
    /**
     * Log the user in
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function login()
    {
        $settings = get_settings('general_settings', true);
        $loginSettings = get_settings('login_settings', true);
        $app_name = get_company_title_with_fallback($settings);

        $this->data['data'] = $settings;
        $this->data['phone_authentication_enabled'] = $loginSettings['phone_authentication_enabled'] ?? 1;
        $this->data['email_authentication_enabled'] = $loginSettings['email_authentication_enabled'] ?? 0;
        $this->data['admin'] = ($this->isLoggedIn && $this->userIsAdmin);

        if ($this->ionAuth->loggedIn()) {
            if ($this->ionAuth->isAdmin()) {
                return redirect()->to('/admin/dashboard')->withCookies();
            } elseif ($this->ionAuth->isHandyman()) {
                return redirect()->to('/handyman/dashboard')->withCookies();
            } else {
                return redirect()->to('/partner/dashboard')->withCookies();
            }
        }

        $this->data['title'] = lang('Auth.login_heading');
        $this->validation->setRule('password', 'Password', 'required');

        if (!$this->request->getPost() || !$this->validation->withRequest($this->request)->run()) {
            return $this->renderLoginPage($app_name);
        }

        // ==============================
        // INPUT
        // ==============================
        $identityInput = trim($this->request->getVar('identity'));
        $password = $this->request->getVar('password');
        $remember = (bool) $this->request->getVar('remember');
        $loginContext = $this->request->getVar('login_context');
        $countryCode = trim((string) $this->request->getVar('country_code'));
        $loginType = null;

        // ==============================
        // THROTTLING CHECK
        // ==============================
        $throttleKey = $identityInput . '|' . $this->request->getIPAddress();

        if ($this->ionAuth->isMaxLoginAttemptsExceeded($throttleKey)) {
            $this->session->setFlashdata('message', 'Too many login attempts. Try again later.');
            return redirect()->back()->withInput();
        }

        // ==============================
        // USER RESOLUTION
        // ==============================
        $db = \Config\Database::connect();
        $builder = $db->table('users u')
            ->select('u.*, ug.group_id')
            ->join('users_groups ug', 'ug.user_id = u.id');

        if ($loginContext === 'partner') {

            $loginType = strtolower(trim((string) $this->request->getVar('login_type')));
            if (!in_array($loginType, ['phone', 'email'], true)) {
                $loginType = filter_var($identityInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            }

            $builder->where('ug.group_id', 3)
                ->where('u.loginType', $loginType);

            if ($loginType === 'email') {
                $builder->where('u.email', $identityInput);
            } else {
                $builder->where('u.phone', $identityInput);
                if (!empty($countryCode)) {
                    $builder->where('u.country_code', $countryCode);
                }
            }

        } elseif ($loginContext === 'handyman') {
            $builder->where('ug.group_id', 4)
                ->where('u.phone', $identityInput)
                ->where('u.deleted_at', null);
            if (!empty($countryCode)) {
                $builder->where('u.country_code', $countryCode);
            }
        } else {
            // Admin login
            $builder->where('ug.group_id', 1)
                ->where('u.phone', $identityInput);
        }

        $user = $builder->get()->getRow();

        if (!$user) {
            $this->ionAuth->increaseLoginAttempts($throttleKey);
            $this->session->setFlashdata('message', labels('invalid_credentials'));
            return redirect()->back()->withInput();
        }

        if ((int) $user->active === 0) {
            $this->session->setFlashdata('message', labels('account_is_inactive'));
            return redirect()->back()->withInput();
        }

        // ==============================
        // PASSWORD VERIFY
        // ==============================
        $ionModel = model(\IonAuth\Models\IonAuthModel::class);

        if (!$ionModel->verifyPassword($password, $user->password, $identityInput)) {
            $this->ionAuth->increaseLoginAttempts($throttleKey);
            $this->session->setFlashdata('message', 'Invalid credentials');
            return redirect()->back()->withInput();
        }

        // ==============================
        // SUCCESS
        // ==============================
        $this->ionAuth->clearLoginAttempts($throttleKey);
        $this->ionAuth->setSession($user);
        $this->ionAuth->updateLastLogin($user->id);

        if ($remember) {
            // Ion Auth rememberUser(identity) updates by phone; duplicate phones update every row
            // with the same selector and hit uc_remember_selector. Scope remember-me to this user id.
            helper('cookie');
            $ionModel->triggerEvents('pre_remember_user');

            // Same sizes as IonAuthModel::generateSelectorValidatorCouple() defaults.
            $selector = bin2hex(random_bytes(20));
            $validator = bin2hex(random_bytes(64));
            $validatorHashed = $ionModel->hashPassword($validator);

            if ($validatorHashed) {
                $usersTable = $this->configIonAuth->tables['users'] ?? 'users';
                $db->table($usersTable)->update(
                    [
                        'remember_selector' => $selector,
                        'remember_code' => $validatorHashed,
                    ],
                    ['id' => (int) $user->id]
                );

                if ($db->affectedRows() > -1) {
                    $expire = $this->configIonAuth->userExpire === 0
                        ? \IonAuth\Models\IonAuthModel::MAX_COOKIE_LIFETIME
                        : $this->configIonAuth->userExpire;

                    set_cookie([
                        'name' => $this->configIonAuth->rememberCookieName,
                        'value' => $selector . '.' . $validator,
                        'expire' => $expire,
                    ]);

                    $ionModel->triggerEvents(['post_remember_user', 'remember_user_successful']);
                } else {
                    $ionModel->triggerEvents(['post_remember_user', 'remember_user_unsuccessful']);
                }
            } else {
                $ionModel->triggerEvents(['post_remember_user', 'remember_user_unsuccessful']);
            }
        }

        $this->applyUserLanguage($user);

        if ($user->group_id == 3 && $loginType !== null) {
            $this->session->set('loginType', $loginType);
        } else {
            $this->session->remove('loginType');
        }

        if ($user->group_id == 3) {
            $partnerUser = fetch_details('users', ['id' => $user->id], ['phone', 'country_code']);
            if (!empty($partnerUser[0])) {
                $this->session->set('partner_phone_at_login', $partnerUser[0]['phone'] ?? '');
                $this->session->set('partner_country_code_at_login', $partnerUser[0]['country_code'] ?? '');
            }
        }

        if ($user->group_id == 1) {
            return redirect()->to('/admin/dashboard')->withCookies();
        } elseif ($user->group_id == 4) {
            return redirect()->to('/handyman/dashboard')->withCookies();
        } else {
            return redirect()->to('/partner/dashboard')->withCookies();
        }
    }

    private function renderLoginPage($app_name)
    {
        $this->data['message'] = $this->validation->getErrors()
            ? $this->validation->listErrors($this->validationListTemplate)
            : $this->session->getFlashdata('message');

        $this->data['identity'] = [
            'name' => 'identity',
            'id' => 'identity',
            'type' => 'text',
            'value' => set_value('identity'),
        ];

        $this->data['password'] = [
            'name' => 'password',
            'id' => 'password',
            'type' => 'password',
        ];

        $this->data['title'] = "Login — $app_name";
        $this->data['main_page'] = "login";

        return $this->renderPage($this->viewsFolder .  '/template', $this->data);
    }

    private function applyUserLanguage($user)
    {
        try {
            $userDetails = fetch_details('users', ['id' => $user->id]);

            if (!empty($userDetails[0]['preferred_language'])) {

                $code = trim($userDetails[0]['preferred_language']);
                $languageExists = fetch_details('languages', ['code' => $code]);

                if (!empty($languageExists)) {
                    $this->session->set('lang', $code);
                    $this->session->set('language', $code);
                    $this->session->set('is_rtl', $languageExists[0]['is_rtl'] ?? 0);

                    \Config\Services::language()->setLocale($code);
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Language preference error: ' . $e->getMessage());
        }
    }
    /**
     * Log the user out
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function logout()
    {
        $this->data['title'] = 'Logout';

        // Load session helper
        helper('session');

        // Add logging for debugging
        // log_message('debug', 'Logout initiated for user: ' . ($this->ionAuth->loggedIn() ? $this->ionAuth->user()->row()->id : 'not logged in'));

        // Check user type BEFORE logout to determine redirect destination
        $isAdmin = false;
        $isHandyman = false;
        $isLoggedIn = false;

        $db = \Config\Database::connect();

        if ($this->ionAuth->loggedIn()) {
            $isLoggedIn = true;
            $isAdmin = $this->ionAuth->isAdmin();
            $isHandyman = $this->ionAuth->isHandyman();
            $user = $this->ionAuth->user()->row();

            if ($this->ionAuth->isPartner()) {
                $platform = 'provider_panel';
            } elseif ($isHandyman) {
                $platform = 'handyman_panel';
            } else {
                $platform = 'admin_panel';
            }

            $db->table('users_fcm_ids')->where('user_id', $user->id)->where('platform', $platform)->delete();
        }

        // Set flash message before logout
        $this->session->setFlashdata('logout_msg', "<ul class='mb-0'><li>Logged Out Successfully</li><ul>");
        $this->is_loggedin = false;

        // Manually destroy session files instead of relying on IonAuth
        $sessionDestroyed = safe_destroy_session();
        $this->ionAuth->logout();

        if ($sessionDestroyed) {
            log_message('debug', 'Session files destroyed successfully');
        }

        // Clear any remaining session data
        $this->session->destroy();

        // log_message('debug', 'Logout completed successfully');

        // Redirect based on the user type we determined BEFORE logout
        if ($isLoggedIn && $isAdmin) {
            return redirect()->to('/admin/login')->withCookies();
        } elseif ($isLoggedIn && $isHandyman) {
            return redirect()->to('/handyman/login')->withCookies();
        } else {
            return redirect()->to('/partner/login')->withCookies();
        }
    }
    /**
     * Change password
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function change_password()
    {
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        if (!$this->ionAuth->loggedIn()) {
            return redirect()->to('/partner/login');
        }
        $this->validation->setRule('old', lang('Auth.change_password_validation_old_password_label'), 'required');
        $this->validation->setRule('new', lang('Auth.change_password_validation_new_password_label'), 'required|min_length[' . $this->configIonAuth->minPasswordLength . ']|matches[new_confirm]');
        $this->validation->setRule('new_confirm', lang('Auth.change_password_validation_new_password_confirm_label'), 'required');
        $user = $this->ionAuth->user()->row();
        $validationPassed = $this->request->getPost() && $this->validation->withRequest($this->request)->run();
        // Password strength (number, uppercase, special) must match frontend indicator
        if ($validationPassed) {
            helper('function');
            $strengthErrors = validate_password_strength($this->request->getPost('new'));
            if (!empty($strengthErrors)) {
                $validationPassed = false;
                $this->session->setFlashdata('message', implode(' ', $strengthErrors));
            }
        }
        if (!$validationPassed) {
            // display the form
            // set the flash data error message if there is one
            $this->data['message'] = ($this->validation->getErrors()) ? $this->validation->listErrors($this->validationListTemplate) : $this->session->getFlashdata('message');
            $this->data['minPasswordLength'] = $this->configIonAuth->minPasswordLength;
            $this->data['old_password'] = [
                'name' => 'old',
                'id' => 'old',
                'type' => 'password',
            ];
            $this->data['new_password'] = [
                'name' => 'new',
                'id' => 'new',
                'type' => 'password',
                'pattern' => '^.{' . $this->data['minPasswordLength'] . '}.*$',
            ];
            $this->data['new_password_confirm'] = [
                'name' => 'new_confirm',
                'id' => 'new_confirm',
                'type' => 'password',
                'pattern' => '^.{' . $this->data['minPasswordLength'] . '}.*$',
            ];
            $this->data['user_id'] = [
                'name' => 'user_id',
                'id' => 'user_id',
                'type' => 'hidden',
                'value' => $user->id,
            ];
            // render
            $this->data['title'] = "Change Password | $app_name - Get Services On Demand";
            $this->data['main_page'] = "auth/change_password";
            $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
            $this->data['meta_description'] = "Change to $app_name. $app_name is one of the leading ";
            return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
        } else {
            $identity = $this->session->get('identity');
            $change = $this->ionAuth->changePassword($identity, $this->request->getPost('old'), $this->request->getPost('new'), $this->userId);
            if ($change) {
                //if the password was successfully changed
                $this->session->setFlashdata('message', $this->ionAuth->messages());
                // Use the logout method which now handles session file deletion
                return $this->logout();
            } else {
                $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
                return redirect()->to('/auth/change_password');
            }
        }
    }
    /**
     * Forgot password
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function forgot_password()
    {
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        $this->data['data'] = $settings;
        $this->data['title'] = lang('Auth.forgot_password_heading');

        // Get login settings for authentication modes - similar to login method
        $loginSettings = get_settings('login_settings', true);
        $this->data['phone_authentication_enabled'] = $loginSettings['phone_authentication_enabled'] ?? 1;
        $this->data['email_authentication_enabled'] = $loginSettings['email_authentication_enabled'] ?? 0;

        // setting validation rules by checking whether identity is username or email
        if ($this->configIonAuth->identity !== 'email') {
            $this->validation->setRule('identity', lang('Auth.forgot_password_identity_label'), 'required');
        } else {
            $this->validation->setRule('identity', lang('Auth.forgot_password_validation_email_label'), 'required|valid_email');
        }
        if (!($this->request->getPost() && $this->validation->withRequest($this->request)->run())) {
            $this->data['type'] = $this->configIonAuth->identity;
            // setup the input
            $this->data['identity'] = [
                'name' => 'identity',
                'id' => 'identity',
            ];
            if ($this->configIonAuth->identity !== 'email') {
                $this->data['identity_label'] = lang('Auth.forgot_password_identity_label');
            } else {
                $this->data['identity_label'] = lang('Auth.forgot_password_email_identity_label');
            }
            // set any errors and display the form
            $this->data['message'] = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : $this->session->getFlashdata('message');
            $this->data['title'] = labels("reset_password", "Reset Password") . " | $app_name";
            $this->data['main_page'] = "forgot_password";
            $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
            $this->data['meta_description'] = "Resetting forgot password of $app_name. $app_name is one of the leading ";
            return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
        } else {
            // $identityColumn = $this->configIonAuth->identity;
            $identity = $this->ionAuth->where("email", $this->request->getPost('identity'))->users()->row();
            $check = (array) $identity;
            if (empty($check)) {
                $this->session->setFlashdata('no_id', '<div class="alert alert-danger" id="infoMessage"><p class="mb-0">' . labels("user_does_not_exist", "User does not Exists") . '</p></div>');
                return redirect()->to('/auth/forgot-password');
            }
            if (empty($identity)) {
                $this->ionAuth->setError('Auth.forgot_password_email_not_found');
                // if ($this->configIonAuth->identity !== 'email') {
                //     $this->ionAuth->setError('Auth.forgot_password_identity_not_found');
                // } else {
                // }
                $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
                return redirect()->to('/auth/forgot-password');
            }
            // run the forgotten password method to email an activation code to the user
            $forgotten = $this->ionAuth->forgottenPassword($identity->{$this->configIonAuth->identity});
            if ($forgotten) {
                // if there were no errors
                $this->session->setFlashdata('message', $this->ionAuth->messages());
                return redirect()->to('/partner/login');
            } else {
                $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
                return redirect()->to('/auth/forgot-password');
            }
        }
    }
    /**
     * Reset password - final step for forgotten password
     *
     * @param string|null $code The reset code
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function reset_password($code = null)
    {
        // $settings = get_settings('general_settings', true);
        // $app_name = (isset($settings['company_title']) && $settings['company_title'] != "") ? $settings['company_title'] : "eDemand";
        // if (!$code) {
        //     throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        // }
        // $this->data['title'] = lang('Auth.reset_password_heading');
        // $user = $this->ionAuth->forgottenPasswordCheck($code);
        // if ($user) {
        //     // if the code is valid then display the password reset form
        //     $this->validation->setRule('new', lang('Auth.reset_password_validation_new_password_label'), 'required|min_length[' . $this->configIonAuth->minPasswordLength . ']|matches[new_confirm]');
        //     $this->validation->setRule('new_confirm', lang('Auth.reset_password_validation_new_password_confirm_label'), 'required');
        //     if (!$this->request->getPost() || $this->validation->withRequest($this->request)->run() === false) {
        //         // display the form
        //         // set the flash data error message if there is one
        //         $this->data['message'] = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : $this->session->getFlashdata('message');
        //         $this->data['minPasswordLength'] = $this->configIonAuth->minPasswordLength;
        //         $this->data['new_password'] = [
        //             'name'    => 'new',
        //             'id'      => 'new',
        //             'type'    => 'password',
        //             'pattern' => '^.{' . $this->data['minPasswordLength'] . '}.*$',
        //         ];
        //         $this->data['new_password_confirm'] = [
        //             'name'    => 'new_confirm',
        //             'id'      => 'new_confirm',
        //             'type'    => 'password',
        //             'pattern' => '^.{' . $this->data['minPasswordLength'] . '}.*$',
        //         ];
        //         $this->data['user_id'] = [
        //             'name'  => 'user_id',
        //             'id'    => 'user_id',
        //             'type'  => 'hidden',
        //             'value' => $user->id,
        //         ];
        //         $this->data['code'] = $code;
        //         // render
        //         $this->data['message'] = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : $this->session->getFlashdata('message');
        //         $this->data['title'] = "Reset password | $app_name - Get Services On Demand";
        //         $this->data['main_page'] = "auth/reset_password";
        //         $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
        //         $this->data['meta_description'] = "Resetting forgot password of $app_name. $app_name is one of the leading ";
        //         return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
        //     } else {
        //         $identity = $user->{$this->configIonAuth->identity};
        //         // do we have a valid request?
        //         if ($user->id != $this->request->getPost('user_id')) {
        //             // something fishy might be up
        //             $this->ionAuth->clearForgottenPasswordCode($identity);
        //             throw new \Exception(lang('Auth.error_security'));
        //         } else {
        //             // finally change the password
        //             $change = $this->ionAuth->resetPassword($identity, $this->request->getPost('new'));
        //             if ($change) {
        //                 // if the password was successfully changed
        //                 $this->session->setFlashdata('message', $this->ionAuth->messages());
        //                 return redirect()->to('/auth/login');
        //             } else {
        //                 $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
        //                 return redirect()->to('/auth/reset-password/' . $code);
        //             }
        //         }
        //     }
        // } else {
        //     // if the code is invalid then send them back to the forgot password page
        //     $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
        //     return redirect()->to('/auth/forgot-password');
        // }
        $validation = \Config\Services::validation();
        $validation->setRules(
            [
                'new_password' => 'required',
                'mobile_number' => 'required',
                'country_code' => 'required',
            ]
        );
        if (!$validation->withRequest($this->request)->run()) {
            $errors = $validation->getErrors();
            $response = [
                'error' => true,
                'message' => $errors,
                'data' => []
            ];
            return $this->response->setJSON($response);
        }
        // Password strength: must match frontend indicator (number, uppercase, special, min length)
        helper('function');
        $strengthErrors = validate_password_strength($this->request->getPost('new_password'));
        if (!empty($strengthErrors)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => $strengthErrors,
                'data' => []
            ]);
        }
        $identity = $this->request->getPost('mobile_number');
        $user_data = fetch_details('users', ['phone' => $identity]);
        if (empty($user_data)) {
            return $this->response->setJSON([
                'error' => false,
                'message' => "User does not exist",
                "data" => $_POST,
            ]);
        }
        if ((($user_data[0]['country_code'] == null) || ($user_data[0]['country_code'] == $this->request->getPost('country_code'))) && (($user_data[0]['phone'] == $identity))) {
            $change = $this->ionAuth->resetPassword($identity, $this->request->getPost('new_password'));
            if ($change) {
                // Load session helper and destroy session files
                helper('session');
                safe_destroy_session();
                return $this->response->setJSON([
                    'error' => false,
                    'message' => "Forgot Password  successfully",
                    "data" => $_POST,
                ]);
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $this->ionAuth->errors($this->validationListTemplate),
                    "data" => $_POST,
                ]);
            }
            $change = $this->ionAuth->resetPassword($identity, $this->request->getPost('new_password'));
            if ($change) {
                // Load session helper and destroy session files
                helper('session');
                safe_destroy_session();
                return $this->response->setJSON([
                    'error' => false,
                    'message' => "Forgot Password  successfully",
                    "data" => $_POST,
                ]);
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $this->ionAuth->errors($this->validationListTemplate),
                    "data" => $_POST,
                ]);
            }
        } else {
            return $this->response->setJSON([
                'error' => true,
                'message' => "Faorgot Password Failed",
                "data" => $_POST,
            ]);
        }
    }
    /**
     * Activate the user
     *
     * @param integer $id   The user ID
     * @param string  $code The activation code
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function activate(int $id, string $code = ''): \CodeIgniter\HTTP\RedirectResponse
    {
        $activation = false;
        if ($code) {
            $activation = $this->ionAuth->activate($id, $code);
        } else if ($this->ionAuth->isAdmin()) {
            $activation = $this->ionAuth->activate($id);
        }
        if ($activation) {
            // redirect them to the auth page
            $this->session->setFlashdata('message', $this->ionAuth->messages());
            return redirect()->to('/auth');
        } else {
            // redirect them to the forgot password page
            $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
            return redirect()->to('/auth/forgot_password');
        }
    }
    /**
     * Deactivate the user
     *
     * @param integer $id The user ID
     *
     * @throw Exception
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function deactivate(int $id = 0)
    {


        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        if (!$this->ionAuth->loggedIn() || !$this->ionAuth->isAdmin()) {
            // redirect them to the home page because they must be an administrator to view this
            throw new \Exception('You must be an administrator to view this page.');
            // TODO : I think it could be nice to have a dedicated exception like '\IonAuth\Exception\NotAllowed
        }
        $this->validation->setRule('confirm', lang('Auth.deactivate_validation_confirm_label'), 'required');
        $this->validation->setRule('id', lang('Auth.deactivate_validation_user_id_label'), 'required|integer');
        if (!$this->validation->withRequest($this->request)->run()) {
            $this->data['user'] = $this->ionAuth->user($id)->row();
            $this->data['title'] = "Deactivate User | $app_name - Get Services On Demand";
            $this->data['main_page'] = "auth/deactivate_user";
            $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
            $this->data['meta_description'] = "Deactivate $app_name account$app_name. $app_name is one of the leading ";
            return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
        } else {
            // do we really want to deactivate?
            if ($this->request->getPost('confirm') === 'yes') {
                // do we have a valid request?
                if ($id !== $this->request->getPost('id', FILTER_VALIDATE_INT)) {
                    throw new \Exception(lang('Auth.error_security'));
                }
                // do we have the right userlevel?
                if ($this->ionAuth->loggedIn() && $this->ionAuth->isAdmin()) {
                    $message = $this->ionAuth->deactivate($id) ? $this->ionAuth->messages() : $this->ionAuth->errors($this->validationListTemplate);
                    $this->session->setFlashdata('message', $message);
                }
            }
            // redirect them back to the auth page
            return redirect()->to('/auth');
        }
    }
    /**
     * Create a new user
     *
     * @return string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function create_user()
    {
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        $this->data['title'] = lang('Auth.create_user_heading');
        if ($this->ionAuth->loggedIn()) {
            return redirect()->to('/auth');
        }
        $tables = $this->configIonAuth->tables;
        $identityColumn = $this->configIonAuth->identity;
        $this->data['identity_column'] = $identityColumn;
        // validate form input
        $this->validation->setRule('first_name', 'Enter First Name', 'required');
        $this->validation->setRule('last_name', 'Enter Last Name', 'required');
        $this->validation->setRule('email', 'Enter Email Address', 'required|valid_email|is_unique[' . $tables['users'] . '.email]');
        $this->validation->setRule('phone', 'Enter Mobile Number', 'required|is_unique[' . $tables['users'] . '.phone]');
        $this->validation->setRule('password', 'Enter Password', 'required||matches[password_confirm]');
        $this->validation->setRule('password_confirm', 'Confirm Password', 'required');
        if ($this->request->getPost() && $this->validation->withRequest($this->request)->run()) {
            $email = strtolower($this->request->getPost('email'));
            $identity = $email;
            $password = $this->request->getPost('password');
            $additionalData = [
                'first_name' => $this->request->getPost('first_name'),
                'last_name' => $this->request->getPost('last_name'),
                'phone' => $this->request->getPost('phone'),
                'country_code' => $this->request->getPost('country_code'),
            ];
            // Password strength: must match frontend indicator (number, uppercase, special, min length)
            helper('function');
            $strengthErrors = validate_password_strength($password);
            if (!empty($strengthErrors)) {
                $this->session->setFlashdata('message', implode(' ', $strengthErrors));
            }
        }
        if ($this->request->getPost() && $this->validation->withRequest($this->request)->run() && empty($strengthErrors ?? []) && $this->ionAuth->register($identity, $password, $email, $additionalData)) {
            // check to see if we are creating the user
            // redirect them back to the admin page
            $this->session->setFlashdata('message', $this->ionAuth->messages());
            return redirect()->to('/auth');
        } else {
            // display the create user form
            // set the flash data error message if there is one
            $this->data['message'] = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : ($this->ionAuth->errors($this->validationListTemplate) ? $this->ionAuth->errors($this->validationListTemplate) : $this->session->getFlashdata('message'));
            $this->data['title'] = "Registration | $app_name - On Demand Services";
            $this->data['main_page'] = "register";
            $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
            $this->data['meta_description'] = "Register to $app_name. $app_name is one of the leading ";
            return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
        }
    }
    /**
     * Redirect a user checking if is admin
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function redirectUser()
    {
        if ($this->ionAuth->isAdmin()) {
            return redirect()->to('/auth');
        }
        return redirect()->to('/');
    }
    /**
     * Edit a user
     *
     * @param integer $id User id
     *
     * @return string string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function edit_user(int $id)
    {
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        $this->data['title'] = lang('Auth.edit_user_heading');
        if (!$this->ionAuth->loggedIn() || (!$this->ionAuth->isAdmin() && !($this->ionAuth->user()->row()->id == $id))) {
            return redirect()->to('/auth');
        }
        $user = $this->ionAuth->user($id)->row();
        $groups = $this->ionAuth->groups()->resultArray();
        $currentGroups = $this->ionAuth->getUsersGroups($id)->getResult();
        if (!empty($_POST)) {
            // validate form input
            $this->validation->setRule('first_name', lang('Auth.edit_user_validation_fname_label'), 'required');
            $this->validation->setRule('last_name', lang('Auth.edit_user_validation_lname_label'), 'required');
            $this->validation->setRule('phone', lang('Auth.edit_user_validation_phone_label'), 'required');
            $this->validation->setRule('company', lang('Auth.edit_user_validation_company_label'), 'required');
            // do we have a valid request?
            if ($id !== $this->request->getPost('id', FILTER_VALIDATE_INT)) {
                throw new \Exception(lang('Auth.error_security'));
            }
            // update the password if it was posted
            if ($this->request->getPost('password')) {
                $this->validation->setRule('password', lang('Auth.edit_user_validation_password_label'), 'required|min_length[' . $this->configIonAuth->minPasswordLength . ']|matches[password_confirm]');
                $this->validation->setRule('password_confirm', lang('Auth.edit_user_validation_password_confirm_label'), 'required');
            }
            if ($this->request->getPost() && $this->validation->withRequest($this->request)->run()) {
                $data = [
                    'first_name' => $this->request->getPost('first_name'),
                    'last_name' => $this->request->getPost('last_name'),
                    'company' => $this->request->getPost('company'),
                    'phone' => $this->request->getPost('phone'),
                ];
                // update the password if it was posted
                if ($this->request->getPost('password')) {
                    $data['password'] = $this->request->getPost('password');
                }
                // Only allow updating groups if user is admin
                if ($this->ionAuth->isAdmin()) {
                    // Update the groups user belongs to
                    $groupData = $this->request->getPost('groups');
                    if (!empty($groupData)) {
                        $this->ionAuth->removeFromGroup('', $id);
                        foreach ($groupData as $grp) {
                            $this->ionAuth->addToGroup($grp, $id);
                        }
                    }
                }
                // check to see if we are updating the user
                if ($this->ionAuth->update($user->id, $data)) {
                    $this->session->setFlashdata('message', $this->ionAuth->messages());
                } else {
                    $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
                }
                // redirect them back to the admin page if admin, or to the base url if non admin
                return $this->redirectUser();
            }
        }
        // display the edit user form
        // set the flash data error message if there is one
        $this->data['message'] = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : ($this->ionAuth->errors($this->validationListTemplate) ? $this->ionAuth->errors($this->validationListTemplate) : $this->session->getFlashdata('message'));
        // pass the user to the view
        $this->data['user'] = $user;
        $this->data['groups'] = $groups;
        $this->data['currentGroups'] = $currentGroups;
        $this->data['first_name'] = [
            'name' => 'first_name',
            'id' => 'first_name',
            'type' => 'text',
            'value' => set_value('first_name', $user->first_name ?: ''),
        ];
        $this->data['last_name'] = [
            'name' => 'last_name',
            'id' => 'last_name',
            'type' => 'text',
            'value' => set_value('last_name', $user->last_name ?: ''),
        ];
        $this->data['company'] = [
            'name' => 'company',
            'id' => 'company',
            'type' => 'text',
            'value' => set_value('company', empty($user->company) ? '' : $user->company),
        ];
        $this->data['phone'] = [
            'name' => 'phone',
            'id' => 'phone',
            'type' => 'text',
            'value' => set_value('phone', empty($user->phone) ? '' : $user->phone),
        ];
        $this->data['password'] = [
            'name' => 'password',
            'id' => 'password',
            'type' => 'password',
        ];
        $this->data['password_confirm'] = [
            'name' => 'password_confirm',
            'id' => 'password_confirm',
            'type' => 'password',
        ];
        $this->data['ionAuth'] = $this->ionAuth;
        $this->data['title'] = "Edit User | $app_name - Get Services On Demand";
        $this->data['main_page'] = "auth/edit_user";
        $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
        $this->data['meta_description'] = "Edit $app_name. $app_name is one of the leading ";
        return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
    }
    /**
     * Create a new group
     *
     * @return string string|\CodeIgniter\HTTP\RedirectResponse
     */
    public function create_group()
    {
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        $this->data['title'] = lang('Auth.create_group_title');
        if (!$this->ionAuth->loggedIn() || !$this->ionAuth->isAdmin()) {
            return redirect()->to('/auth');
        }
        // validate form input
        $this->validation->setRule('group_name', lang('Auth.create_group_validation_name_label'), 'required|alpha_dash');
        if ($this->request->getPost() && $this->validation->withRequest($this->request)->run()) {
            $newGroupId = $this->ionAuth->createGroup($this->request->getPost('group_name'), $this->request->getPost('description'));
            if ($newGroupId) {
                // check to see if we are creating the group
                // redirect them back to the admin page
                $this->session->setFlashdata('message', $this->ionAuth->messages());
                return redirect()->to('/auth');
            }
        } else {
            // display the create group form
            // set the flash data error message if there is one
            $this->data['message'] = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : ($this->ionAuth->errors($this->validationListTemplate) ? $this->ionAuth->errors($this->validationListTemplate) : $this->session->getFlashdata('message'));
            $this->data['group_name'] = [
                'name' => 'group_name',
                'id' => 'group_name',
                'type' => 'text',
                'value' => set_value('group_name'),
            ];
            $this->data['description'] = [
                'name' => 'description',
                'id' => 'description',
                'type' => 'text',
                'value' => set_value('description'),
            ];
            // render
            $this->data['title'] = "Create new user group | $app_name - Get Services On Demand";
            $this->data['main_page'] = "auth/create_group";
            $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
            $this->data['meta_description'] = "Create new group for $app_name. $app_name is one of the leading ";
            return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
        }
    }
    /**
     * Edit a group
     *
     * @param integer $id Group id
     *
     * @return string|CodeIgniter\Http\Response
     */
    public function edit_group(int $id = 0)
    {
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        // bail if no group id given
        if (!$id) {
            return redirect()->to('/auth');
        }
        $this->data['title'] = lang('Auth.edit_group_title');
        if (!$this->ionAuth->loggedIn() || !$this->ionAuth->isAdmin()) {
            return redirect()->to('/auth');
        }
        $group = $this->ionAuth->group($id)->row();
        // validate form input
        $this->validation->setRule('group_name', lang('Auth.edit_group_validation_name_label'), 'required|alpha_dash');
        if ($this->request->getPost()) {
            if ($this->validation->withRequest($this->request)->run()) {
                $groupUpdate = $this->ionAuth->updateGroup($id, $this->request->getPost('group_name'), ['description' => $this->request->getPost('group_description')]);
                if ($groupUpdate) {
                    $this->session->setFlashdata('message', lang('Auth.edit_group_saved'));
                } else {
                    $this->session->setFlashdata('message', $this->ionAuth->errors($this->validationListTemplate));
                }
                return redirect()->to('/auth');
            }
        }
        // set the flash data error message if there is one
        $this->data['message'] = $this->validation->listErrors($this->validationListTemplate) ?: ($this->ionAuth->errors($this->validationListTemplate) ?: $this->session->getFlashdata('message'));
        // pass the user to the view
        $this->data['group'] = $group;
        $readonly = $this->configIonAuth->adminGroup === $group->name ? 'readonly' : '';
        $this->data['group_name'] = [
            'name' => 'group_name',
            'id' => 'group_name',
            'type' => 'text',
            'value' => set_value('group_name', $group->name),
            $readonly => $readonly,
        ];
        $this->data['group_description'] = [
            'name' => 'group_description',
            'id' => 'group_description',
            'type' => 'text',
            'value' => set_value('group_description', $group->description),
        ];
        $this->data['title'] = "Edit Group | $app_name - Get Services On Demand";
        $this->data['main_page'] = "auth/edit_group";
        $this->data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
        $this->data['meta_description'] = "Edit to $app_name. $app_name is one of the leading ";
        return $this->renderPage($this->viewsFolder . DIRECTORY_SEPARATOR . 'template', $this->data);
    }
    /**
     *  THIS WILL CREATE PARTNERS
     */
    public function create_partner()
    {
        // print_r($this->request->getPost('store_country_code'));
        // die;
        $this->user = new Users_model();
        $settings = get_settings('general_settings', true);
        // Get company title with proper language fallback
        $app_name = get_company_title_with_fallback($settings);
        $this->data['title'] = lang('Auth.create_user_heading');
        if ($this->ionAuth->loggedIn()) {
            return redirect()->to('/auth');
        }
        $tables = $this->configIonAuth->tables;
        $identityColumn = $this->configIonAuth->identity;
        $this->data['identity_column'] = $identityColumn;
        // validate form input (email uniqueness is checked below scoped to partners + loginType, not global)
        $this->validation->setRule('username', 'Enter First Name', 'required');
        $this->validation->setRule('email', 'Enter Email Address', 'required|valid_email');
        $this->validation->setRule('phone', 'Enter Mobile Number', 'required');
        $this->validation->setRule('password', 'Enter Password', 'required|matches[password_confirm]');
        $this->validation->setRule('password_confirm', 'Confirm Password', 'required');
        $this->validation->setRule('company_name', 'Enter Company Name', 'required');
        $additionalData = [];
        $company = '';
        $phone = '';
        $user_name = '';
        // loginType is stored based on how the user was verified (same as partner API / login).
        // Form sets this after OTP success: email OTP => loginType=email, phone OTP => loginType=phone.
        $loginType = strtolower(trim((string) $this->request->getPost('loginType')));
        if (!in_array($loginType, ['phone', 'email'], true)) {
            $loginType = 'phone';
        }
        // Normalize email early so we can safely use it in duplicate checks below.
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($this->validation->withRequest($this->request)->run()) {
            $email = strtolower($this->request->getPost('email'));
            $identity = $email;
            $password = $this->request->getPost('password');
            $company = $this->request->getPost('company_name');
            $phone = $this->request->getPost('phone');
            $user_name = $this->request->getPost('username');
            $additionalData = [
                'username' => $user_name,
                'phone' => $this->request->getPost('phone'),
                'company' => $company,
                'country_code' => $this->request->getPost('store_country_code'),
                'loginType' => $loginType,
            ];
        }
        $group_id = [
            'id' => 3
        ];
        $rules = [
            'email' => [
                "rules" => 'required|valid_email',
                "errors" => [
                    "required" => "Please enter email address"
                ]
            ],
            'phone' => [
                "rules" => 'required',
                "errors" => [
                    "required" => "Enter Mobile Number"
                ]
            ],
            'password' => [
                "rules" => 'required|matches[password_confirm]',
                "errors" => [
                    "required" => "Enter Password",
                    'matches' => 'The password confirmation does not match the password'
                ]
            ],
            'password_confirm' => [
                "rules" => 'required',
                "errors" => [
                    "required" => "Enter Confirm Password"
                ]
            ],
            'company_name' => [
                "rules" => 'required',
                "errors" => [
                    "required" => "Enter Company Name"
                ]
            ],
        ];
        // Login-type-aware uniqueness check:
        // - email login: email must be unique among partners
        // - phone login: country_code + phone must be unique among partners
        $db = \Config\Database::connect();
        $countryCode = $this->request->getPost('store_country_code');

        if ($loginType === 'phone') {
            $builder = $db->table('users u');
            $builder->select('u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.loginType', 'phone')
                ->where('u.phone', $phone)
                ->where('u.country_code', $countryCode);
            $duplicateData = $builder->get()->getResultArray();
            if (!empty($duplicateData)) {
                $_SESSION['toastMessage'] = "Phone number already exists please use another one";
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('/auth/create_user')->withCookies();
            }
        } else {
            $builder = $db->table('users u');
            $builder->select('u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.loginType', 'email')
                ->where('u.email', $email);
            $duplicateData = $builder->get()->getResultArray();
            if (!empty($duplicateData)) {
                $_SESSION['toastMessage'] = "Email already exists please use another one";
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('/auth/create_user')->withCookies();
            }
        }
        $this->validation->setRules($rules);
        if (!$this->validation->withRequest($this->request)->run()) {
            // Show validation errors as toast instead of full page.
            $errorMsg = $this->validation->getErrors() ? $this->validation->listErrors($this->validationListTemplate) : ($this->ionAuth->errors($this->validationListTemplate) ? $this->ionAuth->errors($this->validationListTemplate) : $this->session->getFlashdata('message'));
            $errorMsg = is_string($errorMsg) ? strip_tags($errorMsg) : 'Please fix the form errors.';
            $_SESSION['toastMessage'] = $errorMsg;
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('/auth/create_user')->withCookies();
        }
        // Password strength: must match frontend indicator (number, uppercase, special, min length)
        helper('function');
        $strengthErrors = validate_password_strength($this->request->getPost('password'));
        if (!empty($strengthErrors)) {
            $_SESSION['toastMessage'] = implode(' ', $strengthErrors);
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('/auth/create_user')->withCookies();
        }
        // Manually create user instead of IonAuth->register() to avoid
        // IonAuth's blanket identity uniqueness check which doesn't consider loginType
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        if ($hashedPassword === false) {
            $_SESSION['toastMessage'] = 'Registration could not be completed. Please try again.';
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('/auth/create_user')->withCookies();
        }

        $userData = [
            'phone' => $phone,
            'username' => $user_name,
            'password' => $hashedPassword,
            'email' => $email,
            'ip_address' => $this->request->getIPAddress(),
            'created_on' => time(),
            'active' => 1,
            'country_code' => $countryCode,
            'loginType' => $loginType,
        ];

        $db->table('users')->insert($userData);
        $id = $db->insertID();

        if (!$id) {
            $_SESSION['toastMessage'] = 'Registration could not be completed. Please try again.';
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('/auth/create_user')->withCookies();
        }

        $db->table('users_groups')->insert([
            'user_id' => $id,
            'group_id' => 3,
        ]);
        $partner_data = [
            'partner_id' => $id,
            'company_name' => $company,
            'is_approved' => 0,
            'slug' => $this->slugService->resolve(
                currentSlug: null,
                inputSlug: null,
                fallbackName: $company,
                table: 'partner_details',
                excludeId: $id
            ),
        ];
        $tempRowDaysIsOpen = array(0, 0, 0, 0, 0, 0, 0);
        $rowsDays = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $tempRowDays = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $tempRowStartTime = array('00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00');
        $tempRowEndTime = array('00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00', '00:00:00');
        for ($i = 0; $i < count($tempRowStartTime); $i++) {
            $partner_timing = [];
            $partner_timing['day'] = $tempRowDays[$i];
            if (isset($tempRowStartTime[$i])) {
                $partner_timing['opening_time'] = $tempRowStartTime[$i];
            }
            if (isset($tempRowEndTime[$i])) {
                $partner_timing['closing_time'] = $tempRowEndTime[$i];
            }
            $partner_timing['is_open'] = $tempRowDaysIsOpen[$i];
            $partner_timing['partner_id'] = $id;
            insert_details($partner_timing, 'partner_timings');
        }
        $data = $this->partner_model->save($partner_data);
        if ($data) {
            // Success: show toast on login page instead of simple flash message.
            $_SESSION['toastMessage'] = 'Registration successful. You can now log in.';
            $_SESSION['toastMessageType'] = 'success';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('/partner/login')->withCookies();
        }
        // Save failed: show error as toast and redirect back to register form.
        $_SESSION['toastMessage'] = 'Registration could not be completed. Please try again.';
        $_SESSION['toastMessageType'] = 'error';
        $this->session->markAsFlashdata('toastMessage');
        $this->session->markAsFlashdata('toastMessageType');
        return redirect()->to('/auth/create_user')->withCookies();
    }
    /**
     * Render the specified view
     *
     * @param string     $view       The name of the file to load
     * @param array|null $data       An array of key/value pairs to make available within the view.
     * @param boolean    $returnHtml If true return html string
     *
     * @return string|void
     */
    protected function renderPage(string $view, $data = null, bool $returnHtml = true)
    {
        $view = str_replace('\\', '/', $view);
        $viewdata = $data ?: $this->data;
        $viewHtml = view($view, $viewdata);
        if ($returnHtml) {
            return $viewHtml;
        } else {
            echo $viewHtml;
        }
    }
    public function check_number()
    {
        $this->validation = \Config\Services::validation();
        $request = \Config\Services::request();
        $number = $request->getPost('number');
        // Scope by loginType so we only conflict when same identity + same method (email OTP vs phone OTP).
        $loginType = strtolower(trim((string) $request->getPost('login_type')));
        if (!in_array($loginType, ['phone', 'email'], true)) {
            $loginType = filter_var($number, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        }

        $db = \Config\Database::connect();
        $builder = $db->table('users u');

        // Support both phone and email verification for provider registration.
        // Check is scoped by loginType so email-login partner does not block phone-login registration.
        $isEmail = filter_var($number, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            $builder->select('u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.loginType', $loginType)
                ->where(['u.email' => $number]);
        } else {
            $builder->select('u.id,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 3)
                ->where('u.loginType', $loginType)
                ->where(['u.phone' => $number]);
        }
        $mobile_data = $builder->get()->getResultArray();
        $authentication_mode = get_settings('general_settings', true);
        if (!empty($mobile_data)) {
            $response['error'] = true;
            $response['message'] = $isEmail
                ? "Email already exists please use another one"
                : "Phone number already exists please use another one";
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = ['authentication_mode' => $authentication_mode['authentication_mode']];
            return $this->response->setJSON($response);
        } else {
            $error = false;
            $message = "number not exist";
            $response['error'] = $error;
            $response['message'] = $message;
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = ['authentication_mode' => $authentication_mode['authentication_mode']];
            return $this->response->setJSON($response);
        }
    }
    public function customer_privacy_policy()
    {
        $settings = get_settings('general_settings', true);
        $this->data['title'] = 'Customer Privacy Policy | ' . $settings['company_title'];
        $this->data['meta_description'] = 'Privacy Policy | ' . $settings['company_title'];
        $this->data['customer_privacy_policy'] = get_settings('customer_privacy_policy', true);
        $this->data['settings'] = $settings;
        return view('view_customer_privacy_policy.php', $this->data);
    }
    public function check_identity_for_forgot_password()
    {
        $this->validation = \Config\Services::validation();
        $request = \Config\Services::request();
        $user = new Users_model();

        // Get identity (can be email or phone number)
        $identity = $request->getPost('identity');
        $userType = $request->getPost('userType');


        // Check if identity is email or phone number
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);

        if ($userType == 'partner') {
            if ($isEmail) {
                $user_data = $user->join('users_groups ug', 'ug.user_id = users.id', 'inner')->where('users.email', $identity)->where('loginType', 'email')->where('ug.group_id', 3)->first();
                $fieldType = 'email';
            } else {
                // Search by phone number
                $user_data = $user->join('users_groups ug', 'ug.user_id = users.id', 'inner')->where('users.phone', $identity)->where('loginType', 'phone')->where('ug.group_id', 3)->first();
                $fieldType = 'phone';
            }
        } elseif ($userType == 'handyman') {
            $user_data = $user->join('users_groups ug', 'ug.user_id = users.id', 'inner')
                ->where('users.phone', $identity)
                ->where('ug.group_id', 4)
                ->where('users.deleted_at', null)
                ->first();
            $fieldType = 'phone';
        } else {
            $user_data = $user->where('phone', $identity)->where('country_code', $request->getPost('country_code'))->first();
            $fieldType = 'phone';
        }

        $authentication_mode = get_settings('general_settings', true);

        // Check if user exists
        if (!empty($user_data)) {
            // User found - this is what we want for forgot password
            $response['error'] = false;
            $response['message'] = $isEmail ?
                labels('email_found', 'Email found') :
                labels('phone_found', 'Phone number found');
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [
                'authentication_mode' => $authentication_mode['authentication_mode'],
                'identity_type' => $fieldType
            ];
            return $this->response->setJSON($response);
        } else {
            // User not found
            $response['error'] = true;
            $response['message'] = $isEmail ?
                labels('email_not_found', 'Email does not exist') :
                labels('phone_not_found', 'Phone number does not exist');
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [
                'authentication_mode' => $authentication_mode['authentication_mode'],
                'identity_type' => $fieldType
            ];
            return $this->response->setJSON($response);
        }
    }
    public function reset_password_otp()
    {
        helper('function');
        $validation = \Config\Services::validation();
        $passwordRules = get_password_rules();
        $minLen = (int) $passwordRules['min_length'];
        $passwordRule = $minLen > 0 ? 'required|min_length[' . $minLen . ']' : 'required';

        $inputIdentity = $this->request->getPost('phone');
        $isEmail = (bool) filter_var($inputIdentity, FILTER_VALIDATE_EMAIL);

        $validation->setRules($isEmail ? [
            'password' => $passwordRule,
            'password_confirm' => 'required',
            'phone' => 'required|valid_email',
        ] : [
            'password' => $passwordRule,
            'password_confirm' => 'required',
            'phone' => 'required',
            'store_country_code' => 'required',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            $errors = $validation->getErrors();
            return $this->response->setJSON([
                'error' => true,
                'message' => array_values($errors)[0],
            ]);
        }

        $password = $this->request->getPost('password');

        $strengthErrors = validate_password_strength($password);
        if (!empty($strengthErrors)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => is_array($strengthErrors) ? $strengthErrors[0] : $strengthErrors,
            ]);
        }

        if ($password !== $this->request->getPost('password_confirm')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('password_mismatch', 'Passwords do not match'),
            ]);
        }

        $userType = $this->request->getPost('userType') ?? 'partner';
        if ($userType === 'admin') {
            $groupId = (int) 1;
        } elseif ($userType === 'partner') {
            $groupId = (int) 3;
        } elseif ($userType === 'handyman') {
            $groupId = (int) 4;
        } else {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('user_not_found', 'User does not exist'),
            ]);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('users u')
            ->select('u.id, u.country_code')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', $groupId);

        if ($isEmail) {
            $builder->where('u.email', $inputIdentity);
            $builder->where('loginType', 'email');
        } else {
            $builder->where('u.phone', $inputIdentity);
            if ($userType !== 'handyman') {
                $builder->where('loginType', 'phone');
            } else {
                $builder->where('u.deleted_at', null);
            }
        }

        $user = $builder->get()->getRowArray();

        if (empty($user)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('user_not_found', 'User does not exist'),
            ]);
        }

        if (!$isEmail) {
            $countryCode = $this->request->getPost('store_country_code');
            if ($user['country_code'] !== null && $user['country_code'] !== $countryCode) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(FORGOT_PASSWORD_FAILED, 'Forgot Password Failed - Invalid country code'),
                ]);
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        if (!$hashedPassword) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }

        $updated = $db->table('users')
            ->where('id', (int) $user['id'])
            ->update(['password' => $hashedPassword]);

        if ($updated) {
            $this->ionAuth->logout();
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(FORGOT_PASSWORD_SUCCESSFULLY, 'Forgot Password successfully'),
                'data' => [],
            ]);
        }

        return $this->response->setJSON([
            'error' => true,
            'message' => labels(FORGOT_PASSWORD_FAILED, 'Forgot Password Failed'),
        ]);
    }
    public function send_sms_otp()
    {

        $mobile = $_POST['number'];
        $country_code = $_POST['country_code'];

        // Note: set_user_otp() now handles creating/updating OTP records automatically
        // No need to manually check or insert records here
        $otp = random_int(100000, 999999);
        $mobile = $_POST['number'];
        $data = set_user_otp($otp, $mobile, $country_code);



        if ($data['error'] == false) {
            $response['error'] = false;
            $response['message'] = "OTP send successfully";
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [];
            return $this->response->setJSON($response);
        } else {

            $error = true;
            $response['error'] = $error;
            $response['message'] = $data['message'];
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [];
            return $this->response->setJSON($response);
        }
    }

    public function send_email_otp()
    {
        // Get email from POST request
        $email = $this->request->getPost('email');

        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('invalid_email', 'Invalid email address'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ]);
        }

        // Generate 6-digit OTP
        $otp = random_int(100000, 999999);

        // Use the existing set_user_otp_email helper function
        // This function handles:
        // 1. Storing OTP in database with channel='email'
        // 2. Sending email via NotificationService with send_otp event
        // 3. Using existing email template for OTP
        $result = set_user_otp_email($email, $otp);

        if ($result['error'] == false) {
            return $this->response->setJSON([
                'error' => false,
                'message' => labels('otp_sent_to_email', 'OTP has been sent to your email'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ]);
        } else {
            return $this->response->setJSON([
                'error' => true,
                'message' => $result['message'] ?? labels('email_send_failed', 'Failed to send OTP. Please try again.'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ]);
        }
    }

    function verify_sms_otp()
    {
        $mobile = $_POST['number'];
        $otp = $_POST['code'];
        // Query using new schema: identity (phone number) and channel (sms)
        $data = fetch_details('otps', ['identity' => $mobile, 'channel' => 'sms', 'otp' => $otp]);
        if (!empty($data)) {
            $response['error'] = false;
            $response['message'] = "OTP verified";
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [];
            return $this->response->setJSON($response);
        } else {
            $response['error'] = true;
            $response['message'] = "OTP not verified";
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            $response['data'] = [];
            return $this->response->setJSON($response);
        }
    }

    function verify_email_otp()
    {
        // Get email and OTP code from POST request
        $email = $this->request->getPost('email');
        $otp = $this->request->getPost('code');

        // Validate inputs
        if (empty($email) || empty($otp)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('invalid_input', 'Invalid input'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ]);
        }

        // Query database for OTP with email channel
        $data = fetch_details('otps', ['identity' => $email, 'channel' => 'email', 'otp' => $otp]);

        if (!empty($data)) {
            // OTP verified successfully
            return $this->response->setJSON([
                'error' => false,
                'message' => labels('otp_verified', 'OTP verified successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ]);
        } else {
            // OTP verification failed
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('otp_verification_failed', 'OTP verification failed. Please check and try again.'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ]);
        }
    }

    public function providerDetails()
    {
        $uri = service('uri');
        $slug = $uri->getSegments()[1];

        $general_settings = get_settings('general_settings', true);
        $app_settings = get_settings('app_settings', true);

        $customerPlayStoreUrl = $app_settings['customer_playstore_url'] ?? '';
        $customerAppStoreUrl = $app_settings['customer_appstore_url'] ?? '';
        $providerPlayStoreUrl = $app_settings['provider_playstore_url'] ?? '';
        $providerAppStoreUrl = $app_settings['provider_appstore_url'] ?? '';
        $appName = $general_settings['schema_for_deeplink'];

        $this->data['customerPlaystoreUrl'] = $customerPlayStoreUrl;
        $this->data['customerAppStoreUrl'] = $customerAppStoreUrl;
        $this->data['providerPlayStoreUrl'] = $providerPlayStoreUrl;
        $this->data['providerAppStoreUrl'] = $providerAppStoreUrl;
        $this->data['appName'] = $appName;



        return view('backend/admin/pages/deep_link.php', $this->data);
    }
}
