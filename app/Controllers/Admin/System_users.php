<?php

namespace App\Controllers\Admin;

use App\Models\System_user_model;
use App\Models\user_permissions_model;
use App\Models\Users_model;
use IonAuth\Models\IonAuthModel;

class system_users extends Admin
{
    public   $validation, $system_users, $db,  $ionAuth, $user_permissions, $users;
    protected $superadmin;

    public function __construct()
    {
        parent::__construct();
        helper(['form', 'url', 'ResponceServices']);
        $this->system_users = new System_user_model();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->user_permissions = new user_permissions_model();
        $this->users = new Users_model();
        $this->superadmin = $this->session->get('email');
    }
    public function index()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('system_users', 'System Users') . ' | ' . labels('admin_panel', 'Admin Panel'), 'system_users');
            $this->data['categories_name'] = fetch_details('categories', [], ['id', 'name']);
            $this->data['users'] = fetch_details('users', [], ['id', 'username']);
            $this->data['notification'] = fetch_details('notifications');
            $edemand = new \Config\Edemand;
            $permissions = $edemand->permissions;
            $this->data['permissions'] =  $permissions;

            return view('backend/admin/template', $this->data);
        } else {
            return redirect('unauthorised');
        }
    }
    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $data = $this->system_users->list(false, $search, $limit, $offset, $sort, $order);
        return $this->system_users->list(false, $search, $limit, $offset, $sort, $order);
    }
    public function deactivate_user()
    {
        try {


            if ($this->isLoggedIn && $this->userIsAdmin) {
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }
                $user_id = $this->request->getPost('user_id');
                $operation =  $this->ionAuth->deactivate($user_id);
                delete_details(['user_id' => $user_id], 'users_tokens');

                if ($operation) {
                    return successResponse(labels(SUCCESSFULLY_DEACTIVATED, "Successfully Deactivated"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(UNSUCCESSFUL_ATTEMPT_TO_DISABLE_THE_USER, "Unsuccessful attempt to disable the user"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/System_users.php - deactivate_user()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function activate_user()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }
                $user_id = $this->request->getPost('user_id');
                $operation =  $this->ionAuth->activate($user_id);
                if ($operation) {
                    return successResponse(labels(SUCCESSFULLY_ACTIVATED, "Successfully activated"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(UNSUCCESSFUL_ATTEMPT_TO_DISABLE_THE_USER, "Unsuccessful attempt to disable the user"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/System_users.php - activate_user()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_user()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                // Check if modifications are allowed in demo mode
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }

                $user_id = $this->request->getPost('user_id');

                // Prevent users from deleting themselves
                // This is a critical security check to prevent self-deletion
                if ($user_id == $this->userId) {
                    return ErrorResponse(labels('cannot_delete_yourself', "Cannot delete yourself"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Get target user's role to check if they are super admin
                $target_user_role = $this->db->table('user_permissions')
                    ->select('role')
                    ->where('user_id', $user_id)
                    ->get()
                    ->getRow();

                // If target user is super admin, check if at least one super admin will remain
                if ($target_user_role && $target_user_role->role == "1") {
                    // Count total super admins in the system
                    $super_admin_count = $this->db->table('user_permissions')
                        ->where('role', '1')
                        ->countAllResults();

                    // If there's only one super admin left, prevent deletion
                    if ($super_admin_count <= 1) {
                        return ErrorResponse(labels('cannot_delete_last_super_admin', "Cannot delete the last super admin. At least one super admin must remain in the system."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }

                // Delete user permissions first
                $builder_user_permisison = $this->db->table('user_permissions');
                $delete_user_permisison = $builder_user_permisison->delete(['user_id' => $user_id]);

                // Delete the user from users table
                $builder_user = $this->db->table('users');
                $delete_user = $builder_user->delete(['id' => $user_id]);

                // Check if both deletions were successful
                if ($delete_user_permisison && $delete_user) {
                    return successResponse(labels(SUCCESS_IN_DELETING_USER, "Success in deleting user"), false, [], [], 200, csrf_token(), csrf_hash());
                    if ($user_id == $this->userId) {
                        $this->ionAuth->logout();
                        return redirect()->to('/admin/login')->withCookies();
                    }
                } else {
                    return ErrorResponse(labels(UNSUCCESSFUL_ATTEMPT_TO_DELETE_THE_USER, "Unsuccessful attempt to delete the user"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/System_users.php - delete_user()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function add_user()
    {
        try {

            if ($this->isLoggedIn && $this->userIsAdmin) {
                $edemand = new \Config\Edemand;
                $permissions = $edemand->permissions;
                setPageInfo($this->data, labels('add_system_user', 'Add System User') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_system_user');
                $builder = $this->db->table('users u')->select('u.id, username, ug.group_id')->join('users_groups ug', 'ug.user_id = u.id')->where('ug.group_id', 1)->get()->getResultArray();
                $users = $builder;
                $this->data['permissions'] =  $permissions;
                $this->data['users'] = $users;
                $this->data['notification'] = fetch_details('notifications');

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/System_users.php - add_user()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function permit()
    {

        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }
                if ($this->request->getPost('user_type') == 'existing_user') {
                    $this->validation->setRules(
                        [
                            'user' => 'required',
                            'role' => 'required',
                        ]
                    );
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors  = $this->validation->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $user_id = $this->request->getPost('user');
                    $check_user  = exists(['user_id' => $user_id], 'user_permissions');
                    if ($check_user) {
                        return ErrorResponse(labels(THIS_USER_WAS_ALREADY_SELECTED_FOR_PERMISSIONS, "This user was already selected for permissions"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    if ($this->request->getPost('user') == "default") {
                        return ErrorResponse(labels(PLEASE_SELECT_USER, "Please select user"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    if ($this->request->getPost('role') == "default") {
                        return ErrorResponse(labels(PLEASE_SELECT_ROLE, "Please select role"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    $user_name = $this->request->getPost('new_user_name');
                    $mobile = $this->request->getPost('phone');
                    $email = $this->request->getPost('mail');
                    $password  = $this->request->getPost('password');
                    $confirm_password  = $this->request->getPost('confirm_password');

                    // Fetch data for both phone and email in a single query
                    // Check if phone or email exists for a system user (group_id = 1)
                    // Only reject if the phone/email belongs to another system user
                    // Allow if phone/email belongs to a different user group (partner, customer, etc.)
                    $builder = $this->db->table('users u');
                    $builder->select('u.*, ug.group_id')
                        ->join('users_groups ug', 'ug.user_id = u.id')
                        ->where('ug.group_id', 1) // Only check for system users (group_id = 1)
                        ->groupStart()
                        ->where('u.phone', $mobile)
                        ->orWhere('u.email', $email)
                        ->groupEnd();
                    $user_data = $builder->get()->getResultArray();

                    // Separate validation for phone number
                    // Only reject if phone exists for another system user
                    $mobile_data = array_filter($user_data, function ($user) use ($mobile) {
                        return isset($user['phone']) && $user['phone'] == $mobile;
                    });
                    if (!empty($mobile_data)) {
                        return ErrorResponse(labels(PHONE_NUMBER_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, "Phone number already exists please use another one"), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    // Separate validation for email
                    // Only reject if email exists for another system user
                    $email_data = array_filter($user_data, function ($user) use ($email) {
                        return isset($user['email']) && $user['email'] == $email;
                    });
                    if (!empty($email_data)) {
                        return ErrorResponse(labels(EMAIL_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, "Email already exists please use another one"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $ion_auth = new IonAuthModel();
                    $data = [
                        'username' => $user_name,
                        'phone' => $mobile,
                        'email' => $email,
                        'active' => isset($_POST['is_approved']) ? 1 : 0,
                        'password' => $ion_auth->hashPassword($password)
                    ];
                    $insert_id =  $this->users->save($data);
                    $user_id = $this->users->getInsertID();
                    $user_group = [
                        'user_id' => $user_id,
                        'group_id' => 1,
                    ];
                    insert_details($user_group, 'users_groups');
                }

                $edemand = new \Config\Edemand;
                $modules = $edemand->permissions;
                $create = [];
                $read = [];
                $update = [];
                $delete = [];

                foreach ($modules as $module => $perms) {
                    if (in_array('create', $perms)) {
                        $create[$module] = ($this->request->getPost($module . '_create') == "true") ? 1 : 0;
                    }
                    if (in_array('read', $perms)) {
                        $read[$module] = ($this->request->getPost($module . '_read') == "true") ? 1 : 0;
                    }
                    if (in_array('update', $perms)) {
                        $update[$module] = ($this->request->getPost($module . '_update') == "true") ? 1 : 0;
                    }
                    if (in_array('delete', $perms)) {
                        $delete[$module] = ($this->request->getPost($module . '_delete') == "true") ? 1 : 0;
                    }
                }
                $permissions = ["create" => $create, "read" => $read, "update" => $update, "delete" => $delete];
                $permission = json_encode($permissions);
                $role = $this->request->getPost('role');
                $data = [
                    'user_id' => $user_id,
                    'role' => $this->request->getPost('role'),
                    'permissions' => ($role == "1") ? NULL : $permission,
                ];
                $save_perms =  $this->user_permissions->save($data);
                if ($role == "1") {
                    $operation =  $this->ionAuth->activate($user_id);
                }
                if ($save_perms) {
                    return successResponse(labels(PERMISSIONS_ADDED, "Permissions Added"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(COULD_NOT_ADD_PERMISSION, "Could not add permission"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/System_users.php - permit()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function edit_permit()
    {
        try {

            if ($this->isLoggedIn && $this->userIsAdmin) {
                $result = checkModificationInDemoMode($this->superadmin);
                if ($result !== true) {
                    return $this->response->setJSON($result);
                }

                // Get user ID and validate
                $user_id = $this->request->getPost('id');
                if (empty($user_id)) {
                    return ErrorResponse(labels('user_id_is_required', "User ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Get and validate user information fields
                $username = $this->request->getPost('edit_username');
                $email = $this->request->getPost('edit_email');
                $phone = $this->request->getPost('edit_phone');

                // Validate required fields
                if (empty($username)) {
                    return ErrorResponse(labels('username_is_required', "Username is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                if (empty($email)) {
                    return ErrorResponse(labels(EMAIL_IS_REQUIRED, "Email is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                if (empty($phone)) {
                    return ErrorResponse(labels(PHONE_NUMBER_IS_REQUIRED, "Phone number is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return ErrorResponse(labels('invalid_email_format', "Invalid email format"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Fetch data for both phone and email in a single query
                // Check if phone or email exists for another system user (group_id = 1)
                // Only reject if the phone/email belongs to another system user
                // Allow if phone/email belongs to a different user group (partner, customer, etc.)
                $builder = $this->db->table('users u');
                $builder->select('u.*, ug.group_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('u.id !=', $user_id) // Exclude current user
                    ->where('ug.group_id', 1) // Only check for system users (group_id = 1)
                    ->groupStart()
                    ->where('u.phone', $phone)
                    ->orWhere('u.email', $email)
                    ->groupEnd();
                $user_data = $builder->get()->getResultArray();

                // Separate validation for email
                // Only reject if email exists for another system user
                $email_data = array_filter($user_data, function ($user) use ($email) {
                    return isset($user['email']) && $user['email'] == $email;
                });
                if (!empty($email_data)) {
                    return ErrorResponse(labels(EMAIL_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, "Email already exists please use another one"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Separate validation for phone number
                // Only reject if phone exists for another system user
                $phone_data = array_filter($user_data, function ($user) use ($phone) {
                    return isset($user['phone']) && $user['phone'] == $phone;
                });
                if (!empty($phone_data)) {
                    return ErrorResponse(labels(PHONE_NUMBER_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, "Phone number already exists please use another one"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Prepare user data for update
                $user_data = [
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                ];

                // Update user information
                $builder_user = $this->db->table('users');
                $update_user = $builder_user->update($user_data, ['id' => $user_id]);

                if (!$update_user) {
                    return ErrorResponse(labels('could_not_update_user_information', "Could not update user information"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Get existing role from user_permissions (role is not being changed)
                $existing_permission = $this->db->table('user_permissions')
                    ->where('user_id', $user_id)
                    ->get()
                    ->getRowArray();

                if (empty($existing_permission)) {
                    return ErrorResponse(labels('user_permissions_not_found', "User permissions not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                $role = $existing_permission['role'];

                $edemand = new \Config\Edemand;
                $modules = $edemand->permissions;
                $create = [];
                $read = [];
                $update = [];
                $delete = [];

                foreach ($modules as $module => $perms) {
                    if (in_array('create', $perms)) {
                        $create[$module] = ($this->request->getPost($module . '_create_edit') == "true") ? 1 : 0;
                    }
                    if (in_array('read', $perms)) {
                        $read[$module] = ($this->request->getPost($module . '_read_edit') == "true") ? 1 : 0;
                    }
                    if (in_array('update', $perms)) {
                        $update[$module] = ($this->request->getPost($module . '_update_edit') == "true") ? 1 : 0;
                    }
                    if (in_array('delete', $perms)) {
                        $delete[$module] = ($this->request->getPost($module . '_delete_edit') == "true") ? 1 : 0;
                    }
                }
                $permissions = ["create" => $create, "read" => $read, "update" => $update, "delete" => $delete];
                $permission = json_encode($permissions);

                // Update permissions (role remains unchanged)
                $data = [
                    'permissions' => ($role == "1") ? NULL : $permission,
                ];
                $builder = $this->db->table('user_permissions');
                $save_perms = $builder->update($data, ['user_id' => $user_id]);
                if ($save_perms) {
                    return successResponse(labels(PERMISSIONS_UPDATED, "Permissions updated"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(COULD_NOT_UPDATE_PERMISSION, "Could not update permission"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/System_users.php - edit_permit()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
