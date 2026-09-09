<?php

namespace App\Models;

use CodeIgniter\Model;

class Users_model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'id',
        'username',
        'active',
        'first_name',
        'last_name',
        'ip_address',
        'password',
        'email',
        'balance',
        'activation_selector',
        'activation_code',
        'forgotten_password_selector',
        'forgotten_password_code',
        'forgotten_password_time',
        'remember_selector',
        'remember_code',
        'created_on',
        'last_login',
        'company',
        'phone',
        'fcm_id',
        'image',
        'api_key',
        'friends_code',
        'referral_code',
        'city_id',
        'city',
        'latitude',
        'longitude',
        'country_code',
        'platform',
        'web_fcm_id',
        'panel_fcm_id',
        'loginType',
        'payable_commision',
        'preferred_language',
    ];
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    protected $useTimestamps = true;
    public function get_records($select_field = '*', $where = '')
    {
        $this->builder()->like("email", $where, 'before');
        $this->builder()->like("first_name", $where);
        $this->builder()->like("last_name", $where);
        $this->builder()->select($select_field);
        $data = [];
        foreach ($this->builder()->get()->getResultArray() as $record) {
            $data[] = array("id" => $record['id'], "email" => $record['email']);
        }
        return $data;
    }
    public  function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'pd.id', $whereIn = [])
    {
        $multipleWhere = '';
        $db      = \Config\Database::connect();
        $builder = $db->table('users u');
        if ($search and $search != '') {
            $multipleWhere = [
                '`u.id`' => $search,
                '`u.username`' => $search,
                '`u.email`' => $search,
                '`u.phone`' => $search,
            ];
        }
        $builder->select(' COUNT(u.id) as `total` ')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', "2");
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($whereIn) && !empty($whereIn)) {
            $builder->whereIn($column_name, $whereIn);
        }
        if (isset($_GET['customer_filter']) && $_GET['customer_filter'] != '') {
            $builder->where('u.active',  $_GET['customer_filter']);
        }
        $user_count = $builder->get()->getResultArray();
        $total = $user_count[0]['total'];
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($whereIn) && !empty($whereIn)) {
            $builder->whereIn($column_name, $whereIn);
        }
        $builder->select('u.*,ug.group_id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', "2");
        if (isset($_GET['customer_filter']) && $_GET['customer_filter'] != '') {
            $builder->where('u.active',  $_GET['customer_filter']);
        }
        $user_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $fileService = service('fileService');

        foreach ($user_record as $row) {
            if ($from_app) {
                $row['image'] = $fileService->url($row['image'], 'profile');
            }
            $profile = '<div class="o-media o-media--middle">';

            $imageUrl = $fileService->url($row['image'], 'profile');

            if (!empty($row['image'])) {
                // Actual image exists. Use unique lightbox group per row so clicking shows only this image,
                // not a mixed gallery of all list images (which caused wrong or blank preview).
                $profile .= '<a href="' . $imageUrl . '" data-lightbox="user-profile-' . (int) $row['id'] . '">';
                $profile .= '<img class="o-media__img images_in_card" src="' . $imageUrl . '" alt="' . $row['id'] . '">';
                $profile .= '</a>';
            } else {
                // Fallback: first name initial
                $initial = strtoupper(substr($row['username'] ?? '', 0, 1) ?: 'U'); // Default 'U' if no first name
                $bgColor = '#' . substr(md5($row['id']), 0, 6); // unique color based on user ID

                $profile .= '<div class="o-media__img images_in_card fallback-initial" '
                    . 'style="width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; '
                    . 'background-color:' . $bgColor . '; color:#fff; font-weight:bold; font-size:20px;" '
                    . 'title="' . htmlspecialchars($row['username'] ?? '') . '">'
                    . $initial
                    . '</div>';
            }

            $email = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $_SESSION['email'] != 'superadmin@gmail.com')
                ? (!empty($row['email']) ? 'wrteam.' . substr($row['email'], 7) : 'WRTEAM.test.com')
                : $row['email'];
            $phone = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $_SESSION['email'] != 'superadmin@gmail.com')
            ?  ((!empty($row['phone'])) ? 'XXXX' . substr($row['phone'], 7) : "XXX-XX-XX  ")  : ((!empty($row['country_code'])) ? $row['country_code'] : '') . $row['phone'];   
            $profile .= '<div class="o-media__body">
                <div class="provider_name_table">' . $row['username'] . '</div>
                <div class="provider_email_table">' . $email . ' - ' . $phone . '</div>
            </div>
            </div>';
            $operations = ($row['active'] == 1) ?
                '<button class="btn btn-warning deactivate_user" title="' . labels('deactivate_customer', 'Deactivate Customer') . '"> <i class="fa fa-ban" aria-hidden="true"></i> </button>'
                :
                '<button class="btn btn-success activate_user" title="' . labels('activate_customer', 'Activate Customer') . '"> <i class="fa fa-check" aria-hidden="true"></i> </button> ';
            $status = ($row['active'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('active', 'Active') . "
                </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('deactive', 'Deactive') . "
                </div>";
            $tempRow['id'] = $row['id'];
            $tempRow['image'] = $profile;
            $tempRow['profile'] = $profile;
            $tempRow['username'] = $row['username'];
            $tempRow['active'] = $status;
            $tempRow['operations'] = $operations;
            $tempRow['email'] = $email;
            $tempRow['phone'] = $phone;
            
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
            return $bulkData;
        }
    }
    public function get_user($user_id, $select_field = '*')
    {
        $this->builder()->select($select_field)->where(['id' => $user_id]);
        $data = $this->builder()->get()->getResultArray();
        return $data;
    }

    public function getEarningSummary(int $userId): array
    {
        $row = $this->builder()
            ->select('admin_commission, balance')
            ->where('id', $userId)
            ->get()->getRowArray();

        return [
            'admin_commission' => $row['admin_commission'] ?? null,
            'balance'          => $row['balance']          ?? null,
        ];
    }
}
