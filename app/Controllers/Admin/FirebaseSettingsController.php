<?php

namespace App\Controllers\Admin;

use App\Models\Settings;

class FirebaseSettingsController extends Admin
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

    public function firebase_settings()
    {
        try {
            if ($this->request->getPost('update')) {
                $result = checkModificationInDemoMode($this->superadmin);
                if (isset($result['error']) && $result['error']) {
                    return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
                }
                $updatedData = $this->request->getPost();
                unset($updatedData[csrf_token()]);
                unset($updatedData['update']);

                $uploadedFile = $this->request->getFile('json_file');
                $json_file = $uploadedFile && $uploadedFile->isValid() && !$uploadedFile->hasMoved()
                    && strtolower($uploadedFile->getExtension()) === 'json';

                if ($json_file) {
                    $path = FCPATH . 'public/';
                    $newName = 'firebase_config.json';
                    if (file_exists($path . $newName)) {
                        unlink($path . $newName);
                    }
                    $uploadedFile->move($path, $newName);
                    $updatedData['json_file'] = $newName;
                } else {
                    $existing = $this->settingsModel->where('variable', 'firebase_settings')->first();
                    $existingData = json_decode($existing['value'] ?? '{}', true) ?: [];
                    $updatedData['json_file'] = $existingData['json_file'] ?? '';
                }

                $json_string = json_encode($updatedData);
                if ($this->settingsModel->upsert('firebase_settings', $json_string)) {
                    return JsonSuccess(labels('Firebase has been successfully updated', 'Firebase has been successfully updated'));
                }
                return JsonError(labels('Unable to update Firebase section', 'Unable to update Firebase section'));
            }

            $row = $this->settingsModel->where('variable', 'firebase_settings')->first();
            if ($row) {
                $settings = json_decode($row['value'], true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('Firebase Settings', 'Firebase Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'firebase_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/FirebaseSettingsController.php - firebase_settings()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
