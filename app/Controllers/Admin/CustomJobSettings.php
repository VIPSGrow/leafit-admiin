<?php

namespace App\Controllers\Admin;

use App\Controllers\Admin\Admin;
use App\Models\Settings;

class CustomJobSettings extends Admin
{
    protected $superadmin;
    protected $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin = $this->session->get('email');
        $this->settingsModel = new Settings();

        helper(['ResponceServices', 'function']);
    }

    public function __destruct()
    {
        $this->data = [];
    }

    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        $existingSettings = get_settings('custom_job_settings', true);

        $this->data['custom_job_settings'] = $existingSettings ?: [];

        setPageInfo(
            $this->data,
            labels('custom_job_settings', 'Custom Job Settings') . ' | ' . labels('admin_panel', 'Admin Panel'),
            'custom_job_settings'
        );

        return view('backend/admin/template', $this->data);
    }

    public function save()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Demo mode restriction
        if (
            $this->superadmin !== "superadmin@gmail.com" &&
            defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0
        ) {
            return $this->setToastAndRedirect(
                labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR),
                'error'
            );
        }

        try {
            $settings = [
                'max_files_allowed' => $this->request->getPost('max_files_allowed'),
                'max_file_size_images' => $this->request->getPost('max_file_size_images'),
                'max_file_size_video' => $this->request->getPost('max_file_size_video'),
                'max_file_size_other' => $this->request->getPost('max_file_size_other'),
                'allow_image_uploads' => $this->request->getPost('allow_image_uploads') ? 1 : 0,
                'allow_video_uploads' => $this->request->getPost('allow_video_uploads') ? 1 : 0,
                'allow_document_uploads' => $this->request->getPost('allow_document_uploads') ? 1 : 0,
            ];

            $jsonValue = json_encode($settings);

            $this->settingsModel->db->query("
                INSERT INTO settings (`variable`, `value`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ", [
                'custom_job_settings',
                $jsonValue
            ]);

            return $this->setToastAndRedirect(
                labels('settings_saved_successfully', 'Settings saved successfully'),
                'success'
            );

        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> CustomJobSettings::save()');

            return $this->setToastAndRedirect(
                labels('something_went_wrong', 'Something went wrong'),
                'error'
            );
        }
    }

    /**
     * Small helper to reduce repeated toast + redirect boilerplate
     */
    private function setToastAndRedirect($message, $type)
    {
        $_SESSION['toastMessage'] = $message;
        $_SESSION['toastMessageType'] = $type;

        $this->session->markAsFlashdata(['toastMessage', 'toastMessageType']);

        return redirect()->to('admin/settings/custom_job_settings')->withCookies();
    }
}