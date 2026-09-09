<?php

namespace App\Libraries;

use IonAuth\Libraries\IonAuth;
use CodeIgniter\Session\Session;

/**
 * Custom IonAuth Library
 * 
 * Extended version of IonAuth that uses our CustomIonAuthModel
 * to support loginType filtering during authentication.
 * 
 * This ensures proper provider authentication when multiple providers
 * share the same phone number but have different loginType values.
 */
class CustomIonAuth extends IonAuth
{
    protected Session $session;
    /**
     * Constructor
     * 
     * Overrides the parent constructor to use our CustomIonAuthModel
     * instead of the default IonAuthModel.
     */
    public function __construct()
    {
        // Check compatibility first
        $this->checkCompatibility();

        // Load config
        $this->config = config('IonAuth');

        // Initialize email service
        $this->email = \Config\Services::email();
        helper('cookie');

        // Initialize session
        $this->session = session();

        // CUSTOM: Use our extended model that supports loginType filtering
        $this->ionAuthModel = new \IonAuth\Models\IonAuthModel();

        // Configure email if needed
        $emailConfig = $this->config->emailConfig;

        if ($this->config->useCiEmail && isset($emailConfig) && is_array($emailConfig)) {
            $this->email->initialize($emailConfig);
        }

        // Trigger initialization events
        $this->ionAuthModel->triggerEvents('library_constructor');
    }

    /**
     * Check if the currently logged in user is a partner.
     *
     * IonAuth has isAdmin() but not isPartner(). This app uses both admin and
     * partner groups, so we add isPartner() here. Uses the same pattern as
     * IonAuth::isAdmin() with config partnerGroup (default: 'partners').
     *
     * @param int $id User id (0 = current session user)
     * @return bool Whether the user is in the partner group
     */
    public function isPartner(int $id = 0): bool
    {
        $this->ionAuthModel->triggerEvents('is_partner');

        $partnerGroup = $this->config->partnerGroup ?? 'partners';

        return $this->loggedIn() && $this->ionAuthModel->inGroup($partnerGroup, $id);
    }

    /**
     * Check if the currently logged in user is a handyman.
     *
     * Bypasses IonAuth's name-based inGroup() lookup — handyman group is
     * identified by group_id 4 everywhere else in the app (see
     * HandymanDetailsModel, ChatApiController), so we match that convention
     * directly instead of resolving the group by name via ion_auth_groups.
     *
     * @param int $id User id (0 = current session user)
     * @return bool Whether the user is in the handyman group
     */
    public function isHandyman(int $id = 0): bool
    {
        if (! $this->loggedIn()) {
            return false;
        }

        $id = $id ?: (int) $this->session->get('user_id');

        $usersGroupsModel = new \App\Models\UsersGroupsModel();

        return $usersGroupsModel->where('user_id', $id)->where('group_id', 4)->countAllResults() > 0;
    }
}
