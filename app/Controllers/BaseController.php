<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\CustomIonAuth;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
class BaseController extends Controller
{
    /**
     * IonAuth library (Custom extended version)
     *
     * @var CustomIonAuth
     */
    protected $ionAuth;
    protected $isLoggedIn;
    protected $user;
    protected $userIsAdmin;
    protected $userIsPartner;
    protected $userIsHandyman;
    protected $userIdentity;
    protected $userId;
    protected $settings;
    // public $appName;
    /**
     * Instance of the main Request object.
     *
     * @var IncomingRequest|CLIRequest
     */
    protected $request;
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['function', 'url', 'form', 'filesystem'];
    /**
     * Constructor.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param LoggerInterface   $logger
     */
    protected $scriptPattern = ['<script>', '</script>', '<?php', '?>', '<?=', '?>'];
    public function removeScript($input = [])
    {
        if (isset($input) && !empty($input)) {
            $ridScript = str_ireplace($this->scriptPattern, "", $input);
            return $ridScript;
        }
        return $input;
    }
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        helper($this->helpers);
        \CodeIgniter\Events\Events::trigger('post_controller_constructor');
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);
        $session = \Config\Services::session();
        $language = \Config\Services::language();
        $default_language = fetch_details('languages', ['is_default' => '1']);

        // Initialize session language and RTL settings if not already set
        if (!$session->get('lang') && !empty($default_language)) {
            $session->set('lang', $default_language[0]['code']);
            $session->set('language', $default_language[0]['code']);
        }

        // Initialize RTL setting if not already set
        if ($session->get('is_rtl') === null && !empty($default_language)) {
            $session->set('is_rtl', $default_language[0]['is_rtl']);
        }

        if ($session->get('lang')) {
            $language->setLocale($session->lang);
        } else if (!empty($default_language)) {
            $language->setLocale(strtoupper($default_language[0]['code']));
        } else {
            $language->setLocale("EN");
        }




        $this->settings = get_settings('general_settings', true);
        if (!empty($this->settings['system_timezone'])) {
            $timezone = $this->settings['system_timezone'];
        } else {
            $timezone = "Asia/Kolkata";
        }
        date_default_timezone_set($timezone); // Added user timezone
        //--------------------------------------------------------------------
        // Preload any models, libraries, etc, here.
        //--------------------------------------------------------------------
        // E.g.: $this->session = \Config\Services::session();
        // Use our custom IonAuth library that supports loginType filtering
        $this->ionAuth = new CustomIonAuth();
        $this->updateUser();
    }
    protected function updateUser()
    {
        $this->isLoggedIn = $this->ionAuth->loggedIn();
        if ($this->isLoggedIn) {
            $user = $this->ionAuth->user()->row();
            if ($user === null) {
                // User was deleted but session is stale — force logout
                $this->ionAuth->logout();
                $this->isLoggedIn = false;
                $this->user = NULL;
                $this->userIsAdmin = NULL;
                $this->userIsPartner = NULL;
                $this->userIsHandyman = NULL;
                $this->userId = NULL;
                $this->userIdentity = NULL;
                return;
            }
            $this->user = $user->first_name;
            $this->userIsAdmin = $this->ionAuth->isAdmin();
            $this->userIsPartner = $this->ionAuth->isPartner();
            $this->userIsHandyman = $this->ionAuth->isHandyman();
            $this->userId = $user->id;
            $this->userIdentity = $user->email;
        } else {
            $this->user = NULL;
            $this->userIsAdmin = NULL;
            $this->userIsPartner = NULL;
            $this->userIsHandyman = NULL;
            $this->userId = NULL;
            $this->userIdentity = NULL;
        }
    }
}
