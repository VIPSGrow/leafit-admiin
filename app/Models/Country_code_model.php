<?php

namespace App\Models;

use CodeIgniter\Model;

class Country_code_model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'country_codes';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'country_name',
        'country_code',
        'calling_code',
        'flag_image',
        'is_default'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    public $base, $admin_id, $db;

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $user_details = [])
    {
        $disk = fetch_current_file_manager();

        $db      = \Config\Database::connect();
        $builder = $db->table('country_codes');
        $sortable_fields = ['id' => 'id', 'country_name' => 'country_name', 'calling_code' => 'calling_code'];

        // Build search conditions consistently
        if (isset($search) && !empty($search)) {
            $builder->groupStart();
            $builder->like('id', $search);
            $builder->orLike('country_name', $search);
            $builder->orLike('calling_code', $search);
            $builder->groupEnd();
        }

        if (isset($_GET['id']) && $_GET['id'] != '') {
            $builder->where('id', $_GET['id']);
        }

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Get total count with same conditions
        $total_query = clone $builder;
        $contry_code_total = $total_query->select('COUNT(id) as total')->get()->getResultArray();
        $total = $contry_code_total[0]['total'];

        // Get data with same conditions
        $contry_code_record = $builder->select('*')->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $settings = fetch_details('country_codes', ['is_default' => 1]);

        foreach ($contry_code_record as $row) {
            if ($disk == 'local_server') {
                $flag_image_path = base_url('public/backend/assets/country_flags/' . $row['flag_image']);
            } else if ($disk == 'aws_s3') {
                $flag_image_path = fetch_cloud_front_url('country_flags', $row['flag_image']);
            } else {
                $flag_image_path = base_url('public/backend/assets/country_flags/' . $row['flag_image']);
            }

            $flag_image = '<div class="o-media o-media--middle">
                        <a href="' . $flag_image_path . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $flag_image_path . '" alt="' . $row['country_name'] . '">
                        </a>';


            $operations = '<a class="dropdown-item  delete-country_code" data-id="' . $row['id'] . '" style="cursor: pointer;"> <i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
            $default_language_value = (isset($settings[0]['id']) && $settings[0]['id'] != '') ? $settings[0]['id'] : '';
            $tempRow['id'] = $row['id'];
            $tempRow['country_name'] = $row['country_name'];
            $tempRow['country_code'] = $row['country_code'];
            $tempRow['flag_image'] = $row['flag_image'] ? $flag_image : '';
            $tempRow['created_at'] = $row['created_at'];
            $tempRow['calling_code'] = $row['calling_code'];
            $tempRow['operations'] = $operations;
            $tempRow['default'] = ($default_language_value == $row['id']) ?
                '<span class="badge badge-secondary"><em class="fa fa-check"></em>' . labels('default', 'Default') . '</span>' :
                '<a class="btn btn-icon btn-sm btn-info text-white store_default_country_code" style="cursor: pointer;" data-id="' . $row['id'] . '">' . labels('set_as_default', 'Set as Default') . '</a>';
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $data['total'] = (empty($total)) ? (string) count($rows) : $total;
            $data['data'] = $rows;
            return $data;
        } else {
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        }
    }

    function getCountryCodeData()
    {
        $country_code_data = $this->findAll();
        return $country_code_data;
    }
}
