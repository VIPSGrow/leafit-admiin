<?php
namespace App\Controllers\Partner;
use App\Models\Partners_model;
class Kyc extends Partner
{
    public $validations, $db;
    public function __construct()
    {
        parent::__construct();
        $this->service = new Partners_model();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
    }
    public function index()
    {
        if ($this->isLoggedIn) {
            $user_id = $this->ionAuth->user()->row()->id;
            setPageInfo($this->data, labels('kyc', 'Kyc') . ' | ' . labels('provider_panel', 'Provider Panel'), 'kyc');
            $this->data['users'] = fetch_details('users', ['id' => $user_id], ['company']);
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
}
