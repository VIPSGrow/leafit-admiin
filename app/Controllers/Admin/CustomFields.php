<?php

namespace App\Controllers\Admin;

use App\Services\CustomFields\CustomFieldsService;
use App\Services\utility\PermissionService;
use Throwable;

/**
 * Custom Fields – Admin UI
 *
 * Manages the custom fields management page (provider KYC, profile, bank details).
 * This controller serves the UI page and reads existing field definitions.
 */
class CustomFields extends Admin
{
    protected CustomFieldsService $customFieldsService;
    protected PermissionService $permissionService;
    protected $superadmin;

    public function __construct()
    {
        parent::__construct();
        $this->customFieldsService = new CustomFieldsService();
        $this->permissionService = new PermissionService();
        $this->superadmin = $this->session->get('email');
    }

    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        if (!$this->permissionService->can($this->userId, 'read', 'custom_fields')) {
            return NoPermission();
        }

        $this->data['custom_fields_permissions'] = [
            'create' => $this->permissionService->can($this->userId, 'create', 'custom_fields'),
            'read' => true,
            'update' => $this->permissionService->can($this->userId, 'update', 'custom_fields'),
            'delete' => $this->permissionService->can($this->userId, 'delete', 'custom_fields'),
        ];

        setPageInfo(
            $this->data,
            labels('custom_fields', 'Custom Fields') . ' | ' . labels('admin_panel', 'Admin Panel'),
            'custom_fields'
        );

        $this->data['custom_fields'] = [];
        // Fetch languages for dynamic label inputs (same pattern as Categories controller).
        // The view will render one label input per language code.
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

    /**
     * List the custom fields in the bootstrap table
     */
    public function list()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }


        try {
            $limit = (int) ($this->request->getGet('limit') !== null && $this->request->getGet('limit') !== ''
                ? $this->request->getGet('limit')
                : 10);
            $offset = (int) ($this->request->getGet('offset') !== null && $this->request->getGet('offset') !== ''
                ? $this->request->getGet('offset')
                : 0);
            $sort = (string) ($this->request->getGet('sort') !== null && $this->request->getGet('sort') !== ''
                ? $this->request->getGet('sort')
                : 'id');
            $order = (string) ($this->request->getGet('order') !== null && $this->request->getGet('order') !== ''
                ? strtoupper((string) $this->request->getGet('order'))
                : 'DESC');
            $search = (string) ($this->request->getGet('search') !== null && $this->request->getGet('search') !== ''
                ? $this->request->getGet('search')
                : '');
            $group = (string) ($this->request->getGet('group') !== null && $this->request->getGet('group') !== ''
                ? $this->request->getGet('group')
                : '');

            $data = $this->customFieldsService->list(
                search: $search,
                limit: $limit,
                offset: $offset,
                sort: $sort,
                order: $order,
                group: $group,
                permissions: [
                    'update' => $this->permissionService->can($this->userId, 'update', 'custom_fields'),
                    'delete' => $this->permissionService->can($this->userId, 'delete', 'custom_fields'),
                ]
            );
            return $this->response->setJSON($data);
        } catch (Throwable $e) {
            log_message('error', '[CFDIAG list] EXCEPTION ' . get_class($e) . ' :: ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    /**
     * Save a new custom field definition.
     */
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

        if (!$this->permissionService->can($this->userId, 'create', 'custom_fields')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission', 'You do not have permission to perform this action'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        try {
            $serviceResult = $this->customFieldsService->create($this->request->getPost());
            return $this->response->setJSON([
                'error' => $serviceResult['error'] ?? true,
                'message' => $serviceResult['message'] ?? labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CustomFields::save() - ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Update an existing custom field definition.
     */
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
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }

        if (!$this->permissionService->can($this->userId, 'update', 'custom_fields')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission', 'You do not have permission to perform this action'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        try {
            $serviceResult = $this->customFieldsService->update($this->request->getPost());
            return $this->response->setJSON([
                'error' => $serviceResult['error'] ?? true,
                'message' => $serviceResult['message'] ?? labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CustomFields::update() - ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Reorder custom fields by updating sort_order based on the posted row sequence.
     */
    public function change_order()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        try {
            $idsInput = $this->request->getPost('ids');

            if (empty($idsInput)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('ids_parameter_is_required', 'IDs parameter is required'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $decodedInput = html_entity_decode($idsInput, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $ids = json_decode($decodedInput, true);

            if ($ids === null || !is_array($ids) || empty($ids)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('invalid_ids_format', 'Invalid IDs format. Expected a JSON array'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            if (!$this->permissionService->can($this->userId, 'update', 'custom_fields')) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('no_permission', 'You do not have permission to perform this action'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $db = \Config\Database::connect();
            $builder = $db->table('custom_fields');

            foreach ($ids as $key => $id) {
                $id = trim($id);
                if (!is_numeric($id) || $id <= 0) {
                    continue;
                }

                $newSortOrder = $key + 1;
                $builder->where('id', $id);
                $builder->update(['sort_order' => $newSortOrder]);
                $builder->resetQuery();
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('order_updated_successfully', 'Order updated successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Exception in CustomFields::change_order() - ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Delete custom field definition.
     */
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
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }

        if (!$this->permissionService->can($this->userId, 'delete', 'custom_fields')) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        try {
            $serviceResult = $this->customFieldsService->delete($this->request->getPost());
            return $this->response->setJSON([
                'error' => $serviceResult['error'] ?? true,
                'message' => $serviceResult['message'] ?? labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Exception in CustomFields::delete() - ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }
}
