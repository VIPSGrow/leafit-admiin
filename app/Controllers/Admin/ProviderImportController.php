<?php

namespace App\Controllers\Admin;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ProviderImportController extends Admin
{
    public $creator_id;

    public function __construct()
    {
        parent::__construct();
        $this->creator_id = $this->userId;
        helper('ResponceServices');
    }

    public function bulk_import()
    {
        // Bulk import requires at least create permission (for insert) or update permission (for update)
        // Check if user has either create or update permission
        $permissionService = new \App\Services\utility\PermissionService();
        $hasCreate = $permissionService->can((int) $this->creator_id, 'create', 'partner');
        $hasUpdate = $permissionService->can((int) $this->creator_id, 'update', 'partner');

        if (!$hasCreate && !$hasUpdate) {
            // Redirect with session message instead of returning JSON response
            // This ensures proper UI alert is shown instead of raw JSON
            $session = \Config\Services::session();
            if ($session) {
                $_SESSION['toastMessage'] = labels(NO_PERMISSION_TO_TAKE_THIS_ACTION, 'Sorry! You are not permitted to use bulk import');
                $_SESSION['toastMessageType'] = 'error';
                $session->markAsFlashdata('toastMessage');
                $session->markAsFlashdata('toastMessageType');
            }
            return redirect()->to(base_url('admin/partners'));
        }
        setPageInfo($this->data, labels('bulk_provider_update', 'Bulk Provider Update') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'bulk_add_partners');
        return view('backend/admin/template', $this->data);
    }
    public function downloadSampleForInsert()
    {
        try {
            $service = new \App\Services\provider\ProviderBulkImportService();
            $sample  = $service->generateInsertSample();
            $tmpPath = $service->writeSampleExcel($sample['headers'], $sample['rows'], 'insert');

            return $this->response->download($tmpPath, null)
                ->setFileName('providers_sample_without_data.xlsx');
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partner.php - bulk_import_provider_sample_file_download()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function bulk_import_provider_upload()
    {
        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return JsonError(labels('please_upload_a_valid_file', "Please upload a valid file"));
        }

        // Determine mode from headers (presence of 'ID' column means update)
        // Copy to temp path with .xlsx extension so Xlsx reader can open it as a zip
        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk_') . '.xlsx';
        copy($file->getTempName(), $tmpPath);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmpPath);
        $headerRow = $spreadsheet->getActiveSheet()
            ->rangeToArray('A1:' . $spreadsheet->getActiveSheet()->getHighestColumn() . '1')[0];
        $headerRow = array_map(fn($h) => strtolower(trim($h ?? '')), $headerRow);
        $spreadsheet->disconnectWorksheets();
        @unlink($tmpPath);

        $mode = in_array('id', $headerRow) ? 'update' : 'insert';

        // Permission check
        $requiredPermission = $mode === 'insert' ? 'create' : 'update';
        if (!(new \App\Services\utility\PermissionService())->can((int) $this->creator_id, $requiredPermission, 'partner')) {
            return JsonError(
                labels(NO_PERMISSION_TO_TAKE_THIS_ACTION, 'Sorry! You are not permitted to ' . $requiredPermission . ' providers')
            );
        }

        try {
            $service = new \App\Services\provider\ProviderBulkImportService();
            return $service->handle($file, $mode);
        } catch (\Throwable $e) {
            // throw $e;
            log_the_responce($e, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partner.php - bulk_import_provider_upload()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function downloadSampleForUpdate()
    {
        try {
            $service = new \App\Services\provider\ProviderBulkImportService();
            $sample  = $service->generateUpdateSample();

            if (empty($sample['rows'])) {
                http_response_code(400);
                echo json_encode(["message" => labels(DATA_NOT_FOUND, 'Data not found')]);
                return;
            }

            $tmpPath = $service->writeSampleExcel($sample['headers'], $sample['rows'], 'update');

            return $this->response->download($tmpPath, null)
                ->setFileName('providers_sample_with_data.xlsx');
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partner.php - downloadSampleForUpdate()');
            http_response_code(500);
            echo json_encode(["message" => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }
}
