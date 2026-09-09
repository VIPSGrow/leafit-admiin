<?php

namespace App\Models;

use CodeIgniter\Model;

class System_user_model extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['username', 'active', 'first_name', 'last_name', 'ip_address', 'password', 'email', 'balance', 'activation_selector', 'activation_code', 'forgotten_password_selector', 'forgotten_password_code', 'forgotten_password_time', 'remember_selector', 'remember_code', 'created_on', 'last_login', 'company', 'phone', 'fcm_id', 'image', 'api_key', 'friends_code', 'referral_code', 'city_id', 'city', 'latitude', 'longitude'];
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'pd.id', $whereIn = [])
    {
        $db      = \Config\Database::connect();
        $values = ['7'];

        // NOTE: We prepare a base query once and reuse it for both the count and the paginated data.
        //       This keeps the where/search conditions identical, which prevents mismatched totals
        //       that previously broke pagination when a search term was provided.
        $searchableColumns = ['u.id', 'u.company', 'u.username', 'u.email', 'u.phone', 'u.city'];
        $baseBuilder = $db->table('users u')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->join('user_permissions up', 'up.user_id = u.id')
            ->where('ug.group_id', 1)
            ->whereNotIn('u.active', $values);

        if (!empty($search)) {
            // Group the OR conditions so they do not override the mandatory joins/filters.
            $baseBuilder->groupStart();
            foreach ($searchableColumns as $column) {
                $baseBuilder->orLike($column, $search);
            }
            $baseBuilder->groupEnd();
        }
        if (!empty($where)) {
            $baseBuilder->where($where);
        }
        if (!empty($whereIn)) {
            $baseBuilder->whereIn($column_name, $whereIn);
        }

        // Clone ensures both builders share the exact same filters without re-declaring them.
        $countBuilder = clone $baseBuilder;
        $totalRow = $countBuilder->selectCount('u.id', 'total')->get()->getRowArray();
        $total = isset($totalRow['total']) ? (int)$totalRow['total'] : 0;

        $dataBuilder = clone $baseBuilder;
        $system_user_record = $dataBuilder->select(' 
            u.*,
            ug.group_id,
            up.user_id,up.role,up.permissions
        ')
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        if ($from_app == false) {
            $db      = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->whereIn('ug.group_id', [3, 1])
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }
        foreach ($system_user_record as $row) {
            $operations = "";
            $operations = '<div class="dropdown">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
            </a><div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            if ($row['role'] == "1") {
                $role = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('super_admin', 'Super Admin') . "
                </div>";

                // Show activate/deactivate options for all super admins (including self)
                $operations .= ($row['active'] == 1) ?
                    '<a class="dropdown-item deactivate-user "><i class="fa fa-ban mr-1 text-danger"></i>' . labels('deactivate_admin', 'Deactivate Admin') . '</a>' :
                    '<a class="dropdown-item activate-user "><i class="fa fa-check mr-1 text-success"></i>' . labels('activate_admin', 'Activate Admin') . '</a>';

                // Add edit and delete options based on permissions and user
                if ($from_app == false) {
                    // Get current user's role to check if they are super admin
                    $current_user_role = $db->table('user_permissions')
                        ->select('role')
                        ->where('user_id', $user1[0]['id'])
                        ->get()
                        ->getRow();

                    // Show edit option for all super admins
                    if ($permissions['update']['system_user'] == 1) {
                        $operations .= '
                        <a class="dropdown-item edit-user " data-toggle="modal" data-target="#edit_permission"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';
                    }

                    // Only show delete option if current user is super admin AND it's not their own account
                    if ($current_user_role && $current_user_role->role == "1" && $user1[0]['id'] != $row['id']) {
                        $operations .= '
                        <a class="dropdown-item delete-user" > <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                    }
                }
            } else if ($row['role'] == "2") {
                $role = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('admin', 'Admin') . "
                </div>";
                if ($from_app == false) {
                    $operations .= ($row['active'] == 1) ?
                        '<a class="dropdown-item deactivate-user "><i class="fa fa-ban mr-1 text-danger"></i>' . labels('deactivate_admin', 'Deactivate Admin') . '</a>' :
                        '<a class="dropdown-item activate-user "><i class="fa fa-check mr-1 text-success"></i>' . labels('activate_admin', 'Activate Admin') . '</a>';
                }
                if ($from_app == false) {
                    if ($permissions['update']['system_user'] == 1) {
                        $operations .= '
                    <a class="dropdown-item edit-user " data-toggle="modal" data-target="#edit_permission"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';
                    }
                    if ($permissions['delete']['system_user'] == 1) {
                        $operations .= '
                        <a class="dropdown-item delete-user" > <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                    }
                }
            } else {
                $role = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('editor', 'Editor') . "
                </div>";
                $operations .= ($row['active'] == 1) ?
                    '<a class="dropdown-item deactivate-user "><i class="fa fa-ban mr-1 text-danger"></i>' . labels('deactivate_editor', 'Deactivate Editor') . '</a>' :
                    '<a class="dropdown-item activate-user "><i class="fa fa-check mr-1 text-success"></i>' . labels('activate_editor', 'Activate Editor') . '</a>';
                if ($from_app == false) {
                    if ($permissions['update']['system_user'] == 1) {
                        $operations .= '
                        <a class="dropdown-item edit-user " data-toggle="modal" data-target="#edit_permission"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit', 'Edit') . '</a>';
                    }
                }
                if ($from_app == false) {
                    if ($permissions['delete']['system_user'] == 1) {
                        $operations .= ' <a class="dropdown-item delete-user" > <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                    }
                }
            }
            $operations .= '</div></div>';
            $tempRow['id'] = $row['id'];
            $tempRow['username'] = $row['username'];
            $tempRow['email'] = ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0)) ? ((!empty($row['email'])) ? 'Wrteam' . substr($row['email'], 7) : "wrteam@test.com")  : $row['email'];
            $tempRow['phone'] = $row['phone'];
            $tempRow['role_a'] = $row['role'];
            $tempRow['role'] = $role;
            $tempRow['permissions'] = $row['permissions'];
            $tempRow['operations'] = $operations;
            if ($from_app == false) {
                $tempRow['created_at'] = $row['created_at'];
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
        }
        return json_encode($bulkData);
    }
}
