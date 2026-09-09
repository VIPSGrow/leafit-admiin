<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * RefundTransaction Model
 * 
 * Handles refund-related transactions from the existing transactions table
 * Focuses only on transactions with transaction_type = 'refund'
 */
class RefundTransaction_model extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    protected $allowedFields = ['transaction_type', 'user_id', 'order_id', 'type', 'txn_id', 'amount', 'status', 'currency_code', 'message', 'partner_id'];

    /**
     * Get refund transactions with related order and user data
     * 
     * @param array $where Additional filter conditions
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param string $sort Sort field
     * @param string $order Sort order (ASC/DESC)
     * @param string $search Search term
     * @return array
     */
    public function getRefundTransactions($where = [], $limit = 10, $offset = 0, $sort = 't.id', $order = 'DESC', $search = '')
    {
        $db = \Config\Database::connect();
        $builder = $db->table('transactions t');

        // Select refund transaction data along with order and user information
        $builder->select('
            t.*, 
            o.final_total as order_total,
            o.status as order_status,
            o.date_of_service,
            u.username as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            up.username as partner_name,
            up.email as partner_email,
            up.phone as partner_phone,
            up.image as partner_image,
            pd.company_name
        ');

        // Join with orders table
        $builder->join('orders o', 'o.id = t.order_id', 'left');

        // Join with users table for customer info
        $builder->join('users u', 'u.id = t.user_id', 'left');

        // Join with partner info
        $builder->join('users up', 'up.id = t.partner_id', 'left');

        $builder->join('partner_details as pd', 'pd.partner_id = t.partner_id', 'left');

        // Only get refund transactions
        $builder->where('t.transaction_type', 'refund');


        // Apply additional filters
        if (!empty($where)) {
            $builder->where($where);
        }

        // Apply search if provided
        if (!empty($search)) {
            $builder->groupStart()
                ->like('t.id', $search)
                ->orLike('t.order_id', $search)
                ->orLike('t.txn_id', $search)
                ->orLike('u.username', $search)
                ->orLike('up.username', $search)
                ->orLike('t.amount', $search)
                ->groupEnd();
        }

        // Get total count for pagination
        $totalBuilder = clone $builder;
        $total = $totalBuilder->countAllResults();

        // Apply sorting and pagination
        $results = $builder->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        // Ensure we always return valid data even if no results
        return [
            'total' => (int)$total,
            'data' => is_array($results) ? $results : []
        ];
    }

    /**
     * Get refund statistics
     * 
     * @return array
     */
    public function getRefundStatistics()
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('transactions');

            // Get counts by status for refunds
            $stats = [];
            $statuses = ['pending', 'success', 'failed', 'processing'];

            foreach ($statuses as $status) {
                $count = $builder->where([
                    'transaction_type' => 'refund',
                    'status' => $status
                ])->countAllResults(false);
                $stats[$status] = $count;
            }

            // Get total refund amount
            $totalAmount = $builder->selectSum('amount')
                ->where('transaction_type', 'refund')
                ->get()
                ->getRowArray();
            $stats['total_amount'] = $totalAmount['amount'] ?? 0;

            // Get refunds by payment method
            $builder = $db->table('transactions');
            $methodStats = $builder->select('type, COUNT(*) as count, SUM(amount) as total_amount')
                ->where('transaction_type', 'refund')
                ->groupBy('type')
                ->get()
                ->getResultArray();

            $stats['by_method'] = $methodStats;

            return $stats;
        } catch (\Exception $e) {
            log_message('error', 'Exception in getRefundStatistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get refund transaction by ID
     * 
     * @param int $id Transaction ID
     * @return array|null
     */
    public function getRefundById($id)
    {
        try {
            $result = $this->getRefundTransactions(['t.id' => $id], 1, 0);
            return !empty($result['data']) ? $result['data'][0] : null;
        } catch (\Exception $e) {
            log_message('error', 'Exception in getRefundById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get refunds by order ID
     * 
     * @param int $orderId
     * @return array
     */
    public function getRefundsByOrderId($orderId)
    {
        try {
            $result = $this->getRefundTransactions(['t.order_id' => $orderId]);
            return $result['data'] ?? [];
        } catch (\Exception $e) {
            log_message('error', 'Exception in getRefundsByOrderId: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update transaction status
     *
     * @param int $transactionId Transaction ID to update
     * @param string $status New status to set
     * @param string|null $message Optional message key for display. Pass 'txn_manual_refund' for admin-
     *        approved manual refunds; defaults to 'txn_refund_processed' for gateway-driven refunds.
     * @return bool True if update successful, false otherwise
     */
    public function updateTransactionStatus($transactionId, $status, $message = 'txn_refund_processed')
    {
        try {
            // Validate input parameters
            if (empty($transactionId) || empty($status)) {
                return false;
            }

            // Make sure we only update refund transactions
            $existing = $this->where([
                'id' => $transactionId,
                'transaction_type' => 'refund'
            ])->first();

            if (!$existing) {
                return false;
            }


            update_details(['isRefunded' => '1'], ['id' => $existing['order_id']], 'orders');

            // Update status and message. Message controls what the admin UI shows (e.g. Manual Refund vs Xendit Refund).
            $result = $this->update($transactionId, [
                'status' => $status,
                'message' => $message,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return $result !== false;
        } catch (\Exception $e) {
            log_message('error', 'Exception in updateTransactionStatus: ' . $e->getMessage());
            return false;
        }
    }
}
