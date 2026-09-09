<?php
namespace App\Controllers\Partner;
use App\Models\Category_model;
use App\Models\Partners_model;

class Categories extends Partner
{
    public $validations, $db;
    protected Category_model $category;


    public function __construct()
    {
        parent::__construct();
        $this->category = new Category_model();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        helper('ResponceServices');
    }
    public function index()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            // $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $this->userId, 'status' => 'active']);
            // if (empty($is_already_subscribe)) {
            //     return redirect('partner/subscription');
            // }
            setPageInfo($this->data, labels('categories', 'Categories') . ' | ' . labels('provider_panel', 'Provider Panel'), 'categories');
            $this->data['categories'] = get_categories_with_translated_names();
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = [];
            $from_app = false;
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $where['parent_id'] = $_POST['id'];
                $from_app = true;
            }
            $data = $this->category->list($from_app, $search, $limit, $offset, $sort, $order, $where);
            // $decodedData = json_decode($data, true); // Decode JSON to array

            return $data;
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/Partner/Categories.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
