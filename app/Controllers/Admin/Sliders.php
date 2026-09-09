<?php

namespace App\Controllers\Admin;

use App\Models\Slider_model;
use App\Services\utility\FileService;
use App\Services\utility\PermissionService;

class Sliders extends Admin
{
    public $sliders, $creator_id;
    protected $superadmin;
    protected $db;
    protected $validation;
    protected FileService $fileService;
    protected PermissionService $permissionService;

    public function __construct()
    {
        parent::__construct();
        $this->sliders = new Slider_model();
        $this->fileService = new FileService();
        $this->permissionService = new PermissionService();
        $this->creator_id = $this->userId;
        $this->db = \Config\Database::connect();
        $this->validation = \Config\Services::validation();
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }
    public function index()
    {
        setPageInfo($this->data, labels('Sliders', 'Sliders') . '  | ' . labels('admin_panel', 'Admin Panel'), 'sliders');
        $this->data['categories_name'] = get_categories_with_translated_names();

        $currentLanguage = $this->db->escape(get_current_language());
        $defaultLanguage = $this->db->escape(get_default_language());
        $providerData = $this->db->table('partner_details pd')
            ->select("
                pd.partner_id,
                COALESCE(
                    tpd_current.company_name,
                    tpd_default.company_name,
                    pd.company_name
                ) AS company_name,
            ")
            ->join('translated_partner_details tpd_current', "tpd_current.partner_id = pd.partner_id AND tpd_current.language_code = $currentLanguage", 'left')
            ->join('translated_partner_details tpd_default', "tpd_default.partner_id = pd.partner_id AND tpd_default.language_code = $defaultLanguage", 'left')
            ->where('pd.is_approved', '1')
            ->get()->getResultArray();

        $this->data['provider_title'] = $providerData;
        return view('backend/admin/template', $this->data);
    }
    public function add_slider()
    {
        try {
            $type = $this->request->getPost('type');
            $common_rules = [
                'app_image' => ["rules" => 'uploaded[app_image]', "errors" => ["uploaded" => labels(THE_APP_IMAGE_FIELD_IS_REQUIRED, "The app_image field is required"),]],
                'web_image' => ["rules" => 'uploaded[web_image]', "errors" => ["uploaded" => labels(THE_WEB_IMAGE_FIELD_IS_REQUIRED, "The web_image field is required"),]]
            ];
            if ($type == "Category" || $type == "provider" || $type == "url" || $type == "typeurl") {
                $specific_rule = '';
                $specific_error = '';
                $string = "";
                if ($type == "Category") {
                    $specific_rule = 'Category_item';
                    $specific_error = 'category';
                    $string = 'select';
                } elseif ($type == "provider") {
                    $specific_rule = 'service_item';
                    $specific_error = 'provider';
                    $string = 'select';
                } elseif ($type == "url") {
                    $specific_rule = 'url';
                    $specific_error = 'url';
                    $string = 'add';
                }
                $specific_rules = [
                    $specific_rule => ["rules" => 'required', "errors" => ["required" => labels("please", "Please") . " " . $string . " " . $specific_error]]
                ];
            } else {
                $specific_rules = [
                    'type' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_TYPE_OF_SLIDER, "Please select type of slider")]]
                ];
            }
            $validation_rules = array_merge($common_rules, $specific_rules);
            $this->validation->setRules($validation_rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return JsonError($errors);
            }
            $name = $this->request->getPost('type');
            $url = "";
            if ($name == "Category") {
                $id = $this->request->getPost('Category_item');
            } else if ($name == "provider") {
                $id = $this->request->getPost('service_item');
            } else if ($name == "url") {
                $url = $this->request->getPost('url');
                $id = "000";
            } else {
                $id = "000";
            }

            $status = $this->request->getPost('slider_switch') == 'on' ? 1 : 0;

            $images = [
                'app_image' => 'app_image',
                'web_image' => 'web_image'
            ];

            $imagePaths = [];
            foreach ($images as $key => $value) {
                $file = $this->request->getFile($key);
                $result = $this->fileService->upload($file, 'sliders');
                if ($result['error']) {
                    return JsonError($result['message']);
                }
                $imagePaths[$value] = $result['path'];
            }



            $data['type'] = $name;
            $data['type_id'] = $id;
            $data['app_image'] = $imagePaths['app_image'];
            $data['web_image'] = $imagePaths['web_image'];
            $data['status'] = $status;
            $data['url'] = $url;

            if ($this->sliders->save($data)) {
                return JsonSuccess(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"));
            }

            return JsonError(labels('error_occured', "An Error Occured"));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - add_slider()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

        $where = [];
        if (isset($_GET['slider_filter']) && !empty($_GET['slider_filter'])) {
            $where['status'] = $_GET['slider_filter'];
        }
        print_r($this->sliders->list(false, $search, $limit, $offset, $sort, $order, $where));
    }
    public function update_slider()
    {
        try {
            $type = $this->request->getPost('type_1');
            $common_rules = [];
            // we mirror add form validation (without forcing image uploads) so edit behaves consistently
            if ($type == "Category" || $type == "provider" || $type == "url" || $type == "typeurl") {
                $specific_rule = '';
                $specific_error = '';
                $string = "";
                if ($type == "Category") {
                    $specific_rule = 'Category_item_1';
                    $specific_error = 'category';
                    $string = 'select';
                } elseif ($type == "provider") {
                    $specific_rule = 'service_item_1';
                    $specific_error = 'provider';
                    $string = 'select';
                } elseif ($type == "url") {
                    $specific_rule = 'url';
                    $specific_error = 'url';
                    $string = 'add';
                }
                $specific_rules = [
                    $specific_rule => ["rules" => 'required', "errors" => ["required" => labels("please", "Please") . " " . $string . " " . $specific_error]]
                ];
            } else {
                $specific_rules = [
                    'type_1' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_TYPE_OF_SLIDER, "Please select type of slider")]]
                ];
            }
            $validation_rules = array_merge($common_rules, $specific_rules);
            $this->validation->setRules($validation_rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return JsonError($errors);
            }
            $id = $this->request->getPost('id');
            $name = $this->request->getPost('type_1');
            $status = $this->request->getPost('edit_slider_switch') == 'on' ? 1 : 0;
            $old_data = $this->sliders->where('id', $id)->first();

            $url = "";
            if ($name == "Category") {
                $type_id = $this->request->getPost('Category_item_1');
            } else if ($name == "provider") {
                $type_id = $this->request->getPost('service_item_1');
            } else if ($name == "url") {
                $url = $this->request->getPost('url');
                $type_id = "000";
            } else {
                $type_id = "000";
            }

            $images = [
                'app_image' => [
                    'file' => $this->request->getFile('app_image'),
                    'old' => $old_data['app_image']
                ],
                'web_image' => [
                    'file' => $this->request->getFile('web_image'),
                    'old' => $old_data['web_image']
                ]
            ];
            $imagePaths = [];
            foreach ($images as $key => $image) {
                $file = $image['file'];
                $oldPath = $image['old'];

                if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                    $imagePaths[$key] = $oldPath;
                    continue;
                }

                if (!$file->isValid()) {
                    return JsonError($file->getErrorString());
                }

                $result = $this->fileService->replace(
                    $oldPath,
                    $file,
                    'sliders'
                );

                if ($result['error']) {
                    return JsonError($result['message']);
                }

                $imagePaths[$key] = $result['path'];
            }
            $data['type'] = $name;
            $data['type_id'] = $type_id;
            $data['app_image'] = $imagePaths['app_image'];
            $data['web_image'] = $imagePaths['web_image'];
            $data['status'] = $status;
            $data['url'] = $url;

            $upd = $this->sliders->update($id, $data);
            if ($upd) {
                return JsonSuccess(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"));
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - update_slider()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function delete_sliders()
    {
        try {
            $id = $this->request->getPost('user_id');
            $old_data = $this->sliders->where('id', $id)->first();
            if (empty($old_data)) {
                return JsonError(labels(DATA_NOT_FOUND, "Data not found"));
            }

            foreach (['app_image', 'web_image'] as $key) {
                if (!empty($old_data[$key])) {
                    $this->fileService->delete('sliders', $old_data[$key]);
                }
            }

            if ($this->sliders->delete(['id' => $id])) {
                return JsonSuccess(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"));
            }
            return JsonError(labels(ERROR_OCCURED, "An error occured"));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - delete_sliders()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
}
