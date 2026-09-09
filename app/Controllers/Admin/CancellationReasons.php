<?php

namespace App\Controllers\Admin;

use App\Services\utility\PermissionService;
use Throwable;

/**
 * Custom Fields – Admin UI
 *
 * Manages the custom fields management page (provider KYC, profile, bank details).
 * This controller serves the UI page and reads existing field definitions.
 */
class CancellationReasons extends Admin
{
    protected PermissionService $permissionService;
    protected $superadmin;

    public function __construct()
    {
        parent::__construct();
        $this->permissionService = new PermissionService();
        $this->superadmin = $this->session->get('email');
    }

    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        if (!$this->permissionService->can($this->userId, 'read', 'cancellation_reasons')) {
            return NoPermission();
        }

        $this->data['type'] = 'cancel';
        $this->data['main_title'] = labels('cancellation_reasons', 'Cancellation Reasons');
        $this->data['add_title'] = labels('add_reasons', 'Add Reasons');
        $this->data['list_title'] = labels('reasons', 'Reasons');

        $this->data['save_url'] = base_url('admin/cancellation_reasons/save');
        $this->data['list_url'] = base_url('admin/cancellation_reasons/list');
        $this->data['update_url'] = base_url('admin/cancellation_reasons/update');
        $this->data['delete_url'] = base_url('admin/cancellation_reasons/delete');

        setPageInfo(
            $this->data,
            $this->data['main_title'] . ' | ' . labels('admin_panel', 'Admin Panel'),
            'reasons'
        );

        $this->data['languages'] = fetch_details(
            'languages',
            [],
            ['id', 'language', 'is_default', 'code'],
            "",
            '0',
            'id',
            'ASC'
        );

        return view('backend/admin/template', $this->data);
    }

    public function save()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('unauthorized_access', 'Unauthorized access'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        if (!$this->permissionService->can($this->userId, 'create', 'cancellation_reasons')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        try {
            $post = $this->request->getPost();
            $labelsByLanguage = $post['reason'] ?? [];
            $fieldData = [
                'needs_additional_info' => isset($post['needs_additional_info']) && $post['needs_additional_info'] === 'on' ? 1 : 0,
            ];

            if (empty($labelsByLanguage) || !is_array($labelsByLanguage)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('reason_field_is_required', 'Reason field is required'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $model = new \App\Models\CancelReasonModel();
            $success = $model->createWithTranslations($fieldData, $labelsByLanguage);

            if ($success) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels('data_saved_successfully', 'Data saved successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('error_occured', 'An Error occurred.'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CancellationReasons::save() - ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    public function list()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        try {
            $limit = (int) ($this->request->getGet('limit') ?? 10);
            $offset = (int) ($this->request->getGet('offset') ?? 0);
            $sort = (string) ($this->request->getGet('sort') ?? 'id');
            $order = (string) ($this->request->getGet('order') ?? 'DESC');
            $search = (string) ($this->request->getGet('search') ?? '');

            if (!$this->permissionService->can($this->userId, 'read', 'cancellation_reasons')) {
                return $this->response->setJSON(['total' => 0, 'rows' => []]);
            }

            $permissions = [
                'update' => $this->permissionService->can($this->userId, 'update', 'cancellation_reasons'),
                'delete' => $this->permissionService->can($this->userId, 'delete', 'cancellation_reasons')
            ];

            $model = new \App\Models\CancelReasonModel();
            $data = $model->getPaginatedList($search, $limit, $offset, $sort, $order, $permissions);

            return $this->response->setJSON($data);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CancellationReasons::list() - ' . $e->getMessage());
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    public function update()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('unauthorized_access', 'Unauthorized access'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        if (!$this->permissionService->can($this->userId, 'update', 'cancellation_reasons')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        try {
            $post = $this->request->getPost();
            $id = (int) ($post['id'] ?? 0);

            $labelsByLanguage = $post['reason'] ?? [];
            $fieldData = [
                'needs_additional_info' => isset($post['needs_additional_info']) && $post['needs_additional_info'] === 'on' ? 1 : 0,
            ];

            if (empty($labelsByLanguage) || !is_array($labelsByLanguage)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('reason_field_is_required', 'Reason field is required'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $model = new \App\Models\CancelReasonModel();
            $success = $model->updateWithTranslations($id, $fieldData, $labelsByLanguage);

            if ($success) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels('data_updated_successfully', 'Data updated successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('error_occured', 'An Error occurred.'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CancellationReasons::update() - ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    public function delete()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('unauthorized_access', 'Unauthorized access'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        if (!$this->permissionService->can($this->userId, 'delete', 'cancellation_reasons')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        try {
            $id = (int) $this->request->getPost('id');
            if ($id <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('invalid_id', 'Invalid ID'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $model = new \App\Models\CancelReasonModel();
            $success = $model->deleteWithRelations($id);

            if ($success) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels('data_deleted_successfully', 'Data deleted successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            return $this->response->setJSON([
                'error' => true,
                'message' => labels('error_occured', 'An Error occurred.'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CancellationReasons::delete() - ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }
}
