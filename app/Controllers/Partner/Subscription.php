<?php

namespace App\Controllers\Partner;

use App\Libraries\Stripe;
use App\Models\Partners_model;
use App\Models\Service_model;

class Subscription extends Partner
{
    public $validations, $db, $service;
    public function __construct()
    {
        parent::__construct();
        $this->service = new Service_model();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        $this->stripe = new Stripe;
        helper('ResponceServices');
    }
    public function index()
    {
        if ($this->isLoggedIn) {
            $user_id = $this->ionAuth->user()->row()->id;
            setPageInfo($this->data, labels('subscription', 'Subscription') . ' | ' . labels('provider_panel', 'Provider Panel'), 'buy_subscription');
            $this->data['users'] = fetch_details('users', ['id' => $user_id]);
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function pre_payment_setup()
    {
        $user_row = $this->ionAuth->user()->row();
        $_POST['user_id'] = $user_row->id;
        if ($_POST['payment_method'] == "stripe") {
            $order = $this->stripe->create_payment_intent(array('amount' => (1000 * 100)));
            $response['client_secret'] = $order['client_secret'];
            $response['id'] = $order['id'];
        }
        $response = [
            'error' => false,
            'message' => labels('client_secret_get_successfully', 'Client Secret Get Successfully') . '!',
            'csrfName' => csrf_token(),
            'csrfHash' => csrf_hash(),
            'data' => [],
            'client_secret' => $order['client_secret'],
            'id' => $order['id'],
            'billing_details' => [
                'name'  => $user_row->username ?? '',
                'email' => $user_row->email ?? '',
                'phone' => $user_row->phone ?? '',
            ],
        ];
        print_r(json_encode($response));
        return false;
    }
    public function subscription_payment()
    {
        if ($this->isLoggedIn) {
            $_POST['user_id'] = $this->ionAuth->user()->row()->id;
            $_POST['customer_email'] = $this->ionAuth->user()->row()->email;
            $response = [
                'error' => false,
                'message' => labels('buy_successfully', 'Buy Successfully') . '!',
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ];
            print_r(json_encode($response));
            return false;
        }
    }
}
