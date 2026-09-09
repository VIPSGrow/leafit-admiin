<?php

namespace App\Controllers\Admin;

class ProviderSubscriptionController extends Admin
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function partner_subscription()
    {
        try {
            helper('function');
            $uri = service('uri');
            $db      = \Config\Database::connect();
            $builder = $db->table('partner_subscriptions ps');
            $partner_id = $uri->getSegments()[3];

            // First get the active partner subscription record
            $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);

            // Then fetch the subscription details with translations from the main subscriptions table
            $active_subscription_details = [];
            if (!empty($active_partner_subscription)) {
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_with_translations = $subscriptionModel->getWithTranslation(
                    $active_partner_subscription[0]['subscription_id'],
                    get_current_language()
                );

                if ($subscription_with_translations) {
                    // Keep partner subscription as base
                    $active_subscription_details[0] = $active_partner_subscription[0];

                    // Add translations under a separate namespace
                    $active_subscription_details[0]['translations'] = $subscription_with_translations;
                } else {
                    $active_subscription_details[0] = $active_partner_subscription[0];
                }
            }

            $symbol =   get_currency();
            $this->data['currency'] = $symbol;
            $this->data['active_subscription_details'] = $active_subscription_details;
            $this->data['partner_id'] = $partner_id;


            // Fetch available subscriptions with translations for current language
            $subscriptionModel = new \App\Models\Subscription_model();
            $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1]);
            $this->data['subscription_details'] = $subscription_details;

            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_subscription', 'Provider Subscription') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_subscription');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_subscription()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function assign_subscription_to_partner()
    {
        try {
            $partner_id = (int) $this->request->getPost('partner_id');
            if ($this->blockedByDemoMode($partner_id)) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }
            $subscription_id = (int) $this->request->getPost('subscription_id');

            $result = (new \App\Services\Provider\ProviderSubscriptionService())
                ->assign($partner_id, $subscription_id);

            if ($result['error']) {
                return JsonError($result['message']);
            }

            session()->setFlashdata('success', $result['message']);
            return redirect()->to('admin/partners/partner_subscription/' . $partner_id);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - assign_subscription_to_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function assign_subscription_to_partner_from_edit_provider()
    {
        try {
            $partner_id = (int) $this->request->getPost('partner_id');
            if ($this->blockedByDemoMode($partner_id)) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }
            $subscription_id = (int) $this->request->getPost('subscription_id');

            $result = (new \App\Services\Provider\ProviderSubscriptionService())
                ->assign($partner_id, $subscription_id);

            if ($result['error']) {
                return JsonError($result['message']);
            }

            return JsonError($result['message']);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - assign_subscription_to_partner_from_edit_provider()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function cancel_subscription_plan()
    {
        try {
            $partner_id = (int) $this->request->getPost('partner_id');

            if ($this->blockedByDemoMode($partner_id)) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }

            $result = (new \App\Services\Provider\ProviderSubscriptionService())->cancel($partner_id);

            session()->setFlashdata('success', $result['message']);
            return redirect()->to('admin/partners/partner_subscription/' . $partner_id);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cancel_subscription_plan()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function cancel_subscription_plan_from_edit_partner()
    {
        try {
            $partner_id = (int) $this->request->getPost('partner_id');

            // Edit-flow guard: edit modal is reachable for a stale tab whose partner was removed.
            $partner_exists = (new \App\Models\Users_model())->where('id', $partner_id)->countAllResults();
            if ($partner_exists === 0) {
                throw new \Exception(labels(PARTNER_NOT_FOUND, "Partner not found"));
            }
            if ($this->blockedByDemoMode($partner_id)) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }

            $result = (new \App\Services\Provider\ProviderSubscriptionService())->cancel($partner_id);

            return JsonError($result['message']);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cancel_subscription_plan_from_edit_partner()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    private function blockedByDemoMode(int $partnerId): bool
    {
        return defined('ALLOW_MODIFICATION')
            && ALLOW_MODIFICATION == 0
            && $partnerId === 50;
    }
    public function all_subscription_list()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('all_subscription', 'All Subscription') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'all_subscription_list');
                $symbol =   get_currency();
                $this->data['currency'] = $symbol;
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $this->data['partner_id'] = $partner_id;
                $subscription_details = fetch_details('subscriptions', ['status' => 1]);
                $this->data['subscription_details'] = $subscription_details;
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - all_subscription_list()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
}
