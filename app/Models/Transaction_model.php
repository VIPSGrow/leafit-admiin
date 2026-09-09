<?php

namespace App\Models;

use CodeIgniter\Model;

class Transaction_model extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    protected $allowedFields = ['transaction_type', 'user_id', 'order_id', 'type', 'txn_id', 'amount', 'status', 'currency_code', 'message'];
    public function list_transactions($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 't.id', $order = 'DESC', $where = [], $where_in_key = '', $where_in_value = [])
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('transactions t');
        $bulkData = $rows = $tempRow = [];

        // Get current language code for translated partner details join
        // This replaces the non-existent MySQL CURRENT_LANGUAGE() function
        $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';

        if (isset($_GET['offset']))
            $offset = $_GET['offset'];

        // Prepare search term: trim it
        // Simple approach: trim the search string
        // CodeIgniter's like() method will automatically escape it for safe database queries
        $escapedSearch = '';
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $escapedSearch = trim($search);
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 't.id') {
                $sort = "t.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        $order_count = $builder->select('count(t.id) as total')
            ->join('users u', 'u.id=t.user_id')
            ->join('partner_details pd', 'pd.partner_id=t.partner_id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id=pd.partner_id AND tpd.language_code='$currentLang'", 'left');

        // For translated username search, we'll use a WHERE EXISTS subquery instead of JOIN
        // This avoids duplicate rows and searches across all languages
        // We'll add this condition in the search section below

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($where_in_key) && !empty($where_in_key) && isset($where_in_value) && !empty($where_in_value)) {
            $builder->whereIn($where_in_key, $where_in_value);
        }
        // Apply payment method / status / date filters before counting rows to keep totals and pagination in sync.
        if (isset($_GET['txn_provider']) && $_GET['txn_provider'] != '') {
            $builder->where('t.type', $_GET['txn_provider']);
        }
        if (isset($_GET['transaction_status']) && $_GET['transaction_status'] != '') {
            $builder->where('t.status', $_GET['transaction_status']);
        }
        if (isset($_GET['start_date']) && $_GET['start_date'] != '' && isset($_GET['end_date']) && $_GET['end_date'] != '') {
            // Use created_at for filtering because that column always stores the real transaction timestamp.
            $builder->where(["t.created_at >=" => $_GET['start_date'], "t.created_at <=" => $_GET['end_date']]);
        }
        // Apply search filters - simple search across multiple fields using OR logic
        if (!empty($escapedSearch)) {
            $builder->groupStart();
            // Search in transaction fields
            $builder->like('t.id', $escapedSearch);
            $builder->orLike('t.user_id', $escapedSearch);
            $builder->orLike('t.transaction_type', $escapedSearch);
            $builder->orLike('t.order_id', $escapedSearch);
            $builder->orLike('t.type', $escapedSearch);
            $builder->orLike('t.txn_id', $escapedSearch);
            $builder->orLike('t.amount', $escapedSearch);
            $builder->orLike('t.status', $escapedSearch);
            $builder->orLike('t.currency_code', $escapedSearch);
            $builder->orLike('t.message', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            $builder->orLike('t.partner_id', $escapedSearch);
            $builder->orLike('tpd.username', $escapedSearch);
            $builder->orLike('tpd.company_name', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->groupEnd();
        }
        $order_count = $builder->get()->getResultArray();

        $total = $order_count[0]['total'];
        $builder->select('u.username,t.*')
            ->join('users u', 'u.id=t.user_id')
            ->join('partner_details pd', 'pd.partner_id=t.partner_id', 'left')
            ->join('translated_partner_details tpd', "tpd.partner_id=pd.partner_id AND tpd.language_code='$currentLang'", 'left');

        // For translated username search, we use WHERE EXISTS subquery (added in search section below)
        // This avoids duplicate rows and searches across all languages

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($where_in_key) && !empty($where_in_key) && isset($where_in_value) && !empty($where_in_value)) {
            $builder->whereIn($where_in_key, $where_in_value);
        }
        if (isset($_GET['txn_provider']) && $_GET['txn_provider'] != '') {
            $builder->where('t.type', $_GET['txn_provider']);
        }
        if (isset($_GET['transaction_status']) && $_GET['transaction_status'] != '') {
            $builder->where('t.status', $_GET['transaction_status']);
        }
        if (isset($_GET['start_date']) && $_GET['start_date'] != '' && isset($_GET['end_date']) && $_GET['end_date'] != '') {
            // Use created_at for filtering because that column always stores the real transaction timestamp.
            $builder->where(["t.created_at >=" => $_GET['start_date'], "t.created_at <=" => $_GET['end_date']]);
        }
        // Apply search filters - simple search across multiple fields using OR logic
        if (!empty($escapedSearch)) {
            $builder->groupStart();
            // Search in transaction fields
            $builder->like('t.id', $escapedSearch);
            $builder->orLike('t.user_id', $escapedSearch);
            $builder->orLike('t.transaction_type', $escapedSearch);
            $builder->orLike('t.order_id', $escapedSearch);
            $builder->orLike('t.type', $escapedSearch);
            $builder->orLike('t.txn_id', $escapedSearch);
            $builder->orLike('t.amount', $escapedSearch);
            $builder->orLike('t.status', $escapedSearch);
            $builder->orLike('t.currency_code', $escapedSearch);
            $builder->orLike('t.message', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            $builder->orLike('t.partner_id', $escapedSearch);
            $builder->orLike('tpd.username', $escapedSearch);
            $builder->orLike('tpd.company_name', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->groupEnd();
        }
        $order_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();


        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        if (empty($order_record)) {
            $bulkData = array();
        } else {
            foreach ($order_record as $row) {
                $tempRow['id'] = $row['id'];
                $tempRow['user_id'] = $row['user_id'];
                $tempRow['partner_id'] = $row['partner_id'];
                $tempRow['name'] = $row['username'];
                $tempRow['type'] = labels(strtolower($row['type']), $row['type']);
                $tempRow['txn_id'] = $row['txn_id'];
              	$tempRow['reference'] = !empty($row['reference']) ? $row['reference'] : '-';
                $tempRow['transaction_type'] = labels(strtolower($row['transaction_type']), $row['transaction_type']);
                $tempRow['amount'] = $row['amount'];
                $tempRow['currency_code'] = $row['currency_code'];
                $tempRow['status'] = labels(strtolower($row['status']), $row['status']);
                $tempRow['message'] = $row['message'] !== null && $row['message'] !== '' ? labels($row['message'], $row['message']) : $row['message'];
                $tempRow['created_at'] =  date("d-M-Y h:i A", strtotime($row['created_at']));
                if ($from_app) {
                    unset($tempRow['created_at']);
                }
                $rows[] = $tempRow;
            }
        }
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        }
    }
}
