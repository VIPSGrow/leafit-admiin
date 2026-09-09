<?php

namespace App\Controllers\Admin;

use App\Models\Settings;

class ApiKeySettingsController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin = $this->session->get('email');
        $this->settingsModel = new Settings();
        helper('ResponceServices');
    }

    public function api_key_settings()
    {
        try {
            if ($this->request->getPost('update')) {

                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                }
                $updatedData = $this->request->getPost();
                unset($updatedData['files']);
                unset($updatedData[csrf_token()]);
                unset($updatedData['update']);

                $updatedData['microsoft_clarity_enabled'] = isset($updatedData['microsoft_clarity_enabled']) && $updatedData['microsoft_clarity_enabled'] == '1' ? '1' : '0';

                $json_string = json_encode($updatedData);
                if ($this->settingsModel->upsert('api_key_settings', $json_string)) {
                    return JsonSuccess(labels('API key section has been successfully updated', 'API key section has been successfully updated'));
                }
                return JsonError(labels('Unable to update API key section', 'Unable to update API key section'));
            }
            $row = $this->settingsModel->where('variable', 'api_key_settings')->first();
            if ($row) {
                $settings = json_decode($row['value'], true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('API key Settings', 'API key Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'api_key_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/ApiKeySettingsController.php - api_key_settings()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
