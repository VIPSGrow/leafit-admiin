<?php

namespace App\Controllers\Admin;

use App\Models\Settings;

class PaymentGatewaySettingsController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin    = $this->session->get('email');
        $this->settingsModel = new Settings();
        helper('ResponceServices');
    }

    public function pg_settings()
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
                        $_SESSION['toastMessage']     = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType'] = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                $updatedData['cod_setting']                      = isset($updatedData['cod_setting']) ? 1 : 0;
                $updatedData['payment_gateway_setting']          = isset($updatedData['payment_gateway_setting']) ? 1 : 0;
                $updatedData['provider_online_payment_setting']  = isset($updatedData['provider_online_payment_setting']) ? 1 : 0;
                $paypal_status       = isset($updatedData['paypal_status']) ? 1 : 0;
                $razorpayApiStatus   = isset($updatedData['razorpayApiStatus']) ? 1 : 0;
                $paystack_status     = isset($updatedData['paystack_status']) ? 1 : 0;
                $stripe_status       = isset($updatedData['stripe_status']) ? 1 : 0;
                $flutterwave_status  = isset($updatedData['flutterwave_status']) ? 1 : 0;
                $xendit_status       = isset($updatedData['xendit_status']) ? 1 : 0;
                $cashfree_status     = isset($updatedData['cashfree_status']) ? 1 : 0;
                if ($updatedData['payment_gateway_setting'] == 1 && $paypal_status == 0 && $razorpayApiStatus == 0 && $paystack_status == 0 && $stripe_status == 0 && $flutterwave_status == 0 && $xendit_status == 0 && $cashfree_status == 0) {
                    $_SESSION['toastMessage']     = labels('At least one payment method must be enabled', 'At least one payment method must be enabled');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/pg-settings')->withCookies();
                }
                unset($updatedData['update']);
                unset($updatedData[csrf_token()]);
                if (isset($updatedData['paypal_website_url'])) {
                    $updatedData['paypal_website_url'] = rtrim($updatedData['paypal_website_url'], '/');
                }
                if (isset($updatedData['flutterwave_website_url'])) {
                    $updatedData['flutterwave_website_url'] = rtrim($updatedData['flutterwave_website_url'], '/');
                }
                if (isset($updatedData['cashfree_website_url'])) {
                    $updatedData['cashfree_website_url'] = rtrim($updatedData['cashfree_website_url'], '/');
                }
                if (isset($updatedData['flutterwave_webhook_secret_key'])) {
                    updateEnv('FLUTTERWAVE_SECRET_KEY', $updatedData['flutterwave_webhook_secret_key']);
                }
                $json_string = json_encode($updatedData);
                if ($this->settingsModel->upsert('payment_gateways_settings', $json_string)) {
                    $_SESSION['toastMessage']     = labels('Payment gateway settings has been successfully updated', 'Payment gateway settings has been successfully updated');
                    $_SESSION['toastMessageType'] = 'success';
                } else {
                    $_SESSION['toastMessage']     = labels('Unable to update the payment gateways settings', 'Unable to update the payment gateways settings');
                    $_SESSION['toastMessageType'] = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/pg-settings')->withCookies();
            }

            $row = $this->settingsModel->where('variable', 'payment_gateways_settings')->first();
            if ($row) {
                $settings     = json_decode($row['value'], true);
                $this->data   = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('Payment Gateways Settings', 'Payment Gateways Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'payment_gateways');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/PaymentGatewaySettingsController.php - pg_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
