<?php

namespace App\Controllers\Admin;

class Profile extends Admin
{
    protected $superadmin, $validation;
    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->superadmin = $this->session->get('email');
    }
    public function index()
    {
        helper('function');
        if (!$this->isLoggedIn) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('Profile', 'Profile') . ' | ' . labels('admin_panel', 'Admin Panel'), 'profile');

        $user = fetch_details('users', ['id' => $this->userId])[0];
        $fileService = service('fileService');

        $hasImage = !empty($user['image']);
        $user['has_profile_image'] = $hasImage;
        $user['profile_image_url'] = $hasImage ? $fileService->url($user['image'], 'profiles') : '';
        $user['profile_initial']   = strtoupper(mb_substr((string) ($user['username'] ?? ''), 0, 1));

        $this->data['data'] = $user;
        return view('backend/admin/template', $this->data);
    }
    public function password()
    {
        helper('function');
        if (!$this->isLoggedIn) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('change_password', 'Change Password') . ' | ' . labels('admin_panel', 'Admin Panel'), 'change_password');
        return view('backend/admin/template', $this->data);
    }

    public function passwordUpdate()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }

            helper('function');
            $oldPwInput     = (string) $this->request->getPost('old_password');
            $newPwInput     = (string) $this->request->getPost('new_password');
            $confirmPwInput = (string) $this->request->getPost('confirm_password');

            $rules = [
                'old_password' => [
                    'rules'  => 'required',
                    'errors' => ['required' => labels('old_password', 'Old Password') . ' ' . labels('is_required', 'is required')],
                ],
                'new_password' => [
                    'rules'  => 'required',
                    'errors' => ['required' => labels('new_password', 'New Password') . ' ' . labels('is_required', 'is required')],
                ],
                'confirm_password' => [
                    'rules'  => 'required|matches[new_password]',
                    'errors' => [
                        'required' => labels('confirm_password', 'Confirm Password') . ' ' . labels('is_required', 'is required'),
                        'matches'  => labels('password_mismatch', 'Password Mismatch'),
                    ],
                ],
            ];
            $this->validation->setRules($rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => true,
                    'message'  => $this->validation->getErrors(),
                    'data'     => [],
                ]);
            }

            $strengthErrors = validate_password_strength($newPwInput);
            if (!empty($strengthErrors)) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => true,
                    'message'  => labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.'),
                ]);
            }

            $existingUser = fetch_details('users', ['id' => $this->userId], ['password']);
            if (empty($existingUser)) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => true,
                    'message'  => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                ]);
            }
            $oldHash = (string) $existingUser[0]['password'];

            if (!password_verify($oldPwInput, $oldHash)) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => true,
                    'message'  => labels('old_password_did_not_match', 'Old password did not match'),
                ]);
            }

            if (password_verify($newPwInput, $oldHash)) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => true,
                    'message'  => labels('new_password_same_as_old', 'New password must be different from old password'),
                ]);
            }

            $status = update_details(
                ['password' => password_hash($newPwInput, PASSWORD_BCRYPT)],
                ['id' => $this->userId],
                'users'
            );

            if (!$status) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => true,
                    'message'  => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                    'data'     => [],
                ]);
            }

            helper('session');
            safe_destroy_session();

            return $this->response->setJSON([
                'csrfName'     => csrf_token(),
                'csrfHash'     => csrf_hash(),
                'error'        => false,
                'message'      => labels('password_updated_successfully', 'Password updated successfully'),
                'data'         => [],
                'redirect_url' => base_url('admin/login'),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> app/Controllers/admin/Profile.php - passwordUpdate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $phoneNumber = trim((string) $this->request->getPost('phone'));
            $emailInput  = trim((string) $this->request->getPost('email'));

            $existingUser = fetch_details('users', ['id' => $this->userId], ['phone', 'email', 'image']);
            if (empty($existingUser)) {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, "Something Went Wrong"),
                ]);
            }
            $oldPhone    = $existingUser[0]['phone'];
            $oldEmail    = $existingUser[0]['email'];
            $old_image   = [['image' => $existingUser[0]['image']]];

            $rules = [
                'username' => [
                    "rules" => 'required|trim',
                    "errors" => [
                        "required" => "Please enter username",
                    ],
                ],
                'email' => [
                    "rules" => 'required|valid_email|is_unique[users.email,id,' . $this->userId . ']',
                    "errors" => [
                        "required"    => "Please enter email",
                        "valid_email" => labels('invalid_email', 'Invalid email address'),
                        "is_unique"   => labels('email_already_taken', 'Email is already in use'),
                    ],
                ],
                'phone' => [
                    "rules" => 'required|is_unique[users.phone,id,' . $this->userId . ']',
                    "errors" => [
                        "required"  => "Please enter admin phone number",
                        "is_unique" => labels('phone_already_taken', 'Phone number is already in use'),
                    ],
                ],
            ];
            $this->validation->setRules($rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                $response['error'] = true;
                $response['message'] = $errors;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['data'] = [];
                return $this->response->setJSON($response);
            }
            $data = [
                'username' => $this->request->getPost('username'),
                'email'    => $emailInput,
                'phone'    => $phoneNumber,
            ];

            $phoneChanged = ((string) $oldPhone !== (string) $phoneNumber);

            $data['image'] = $old_image[0]['image'];
            $uploaded = $this->request->getFile('profile');
            if ($uploaded && $uploaded->isValid() && !$uploaded->hasMoved()) {
                $fileService = service('fileService');
                $result = $fileService->replace($old_image[0]['image'] ?? null, $uploaded, 'profiles');
                if (!empty($result['error'])) {
                    return $this->response->setJSON([
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'error'    => true,
                        'message'  => $result['message'] ?: labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                    ]);
                }
                $data['image'] = $result['path'];
            }
            $status = update_details(
                $data,
                ['id' => $this->userId],
                'users'
            );
            if ($status) {
                $response = [
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error'    => false,
                    'message'  => labels('User updated successfully', 'User updated successfully'),
                    'data'     => [],
                ];

                if ($phoneChanged) {
                    helper('session');
                    safe_destroy_session();
                    $response['redirect_url'] = base_url('admin/login');
                }

                return $this->response->setJSON($response);
            } else {
                return $this->response->setJSON([
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, "Something Went Wrong"),
                    "data" => [],
                ]);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Profile.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
