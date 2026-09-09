<?php

namespace App\Services\Provider;

use App\Models\HandymanCashCollectionModel;
use App\Models\Users_model;
use Config\Database;

/**
 * Owns the "provider collects cash from handyman" workflow — the deferred
 * counterpart to the partner-side cash_collection/payable_commision writes
 * that used to fire unconditionally at COD booking completion, now deferred
 * until the partner confirms they physically received (some or all of) the
 * cash back from the handyman who completed the booking.
 */
class HandymanCashCollectionService
{
    private HandymanCashCollectionModel $ledger;
    private Users_model $users;

    public function __construct()
    {
        helper('function'); // labels / add_settlement_cashcollection_history / get_admin_commision
        $this->ledger = new HandymanCashCollectionModel();
        $this->users  = new Users_model();
    }

    /**
     * Record that the partner received `$amount` in cash from `$handymanId`.
     * Writes a settlement (debit) row against the handyman's ledger, then
     * fires the same cash_collection/payable_commision/settlement_cashcollection_history
     * writes the original flow always made — just at the real moment of
     * transfer instead of at booking completion, and for whatever amount the
     * partner actually collected rather than a specific order.
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable
     */
    public function collectFromHandyman(int $partnerId, int $handymanId, float $amount, ?string $message): array
    {
        if ($amount <= 0) {
            return ['error' => true, 'message' => labels(AMOUNT_MUST_BE_GREATER_THAN_0, 'Amount must be greater than 0')];
        }

        $outstanding = $this->ledger->getOutstandingTotal($handymanId);
        if ($amount > $outstanding) {
            return ['error' => true, 'message' => labels(AMOUNT_MUST_BE_LESS_THAN_OR_EQUAL_TO_CURRENT_BALANCE, 'Amount must be less than or equal to current balance')];
        }

        $adminCommissionPct = (float) get_admin_commision($partnerId);
        $commission = $amount * $adminCommissionPct / 100;
        $orderId = $this->ledger->getLatestCollectionOrderId($handymanId);

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            $this->ledger->recordSettlement($handymanId, $partnerId, $amount, $message, $orderId);

            $db->table('cash_collection')->insert([
                'user_id'    => $handymanId,
                'order_id'   => $orderId,
                'message'    => $message ?: 'provider received cash from handyman',
                'status'     => 'provider_cash_recevied',
                'commison'   => (int) $commission,
                'partner_id' => $partnerId,
                'date'       => date('Y-m-d'),
            ]);

            $current = $this->users->select(['payable_commision'])->find($partnerId);
            $this->users->update($partnerId, [
                'payable_commision' => ((float) ($current['payable_commision'] ?: 0)) + $commission,
            ]);

            add_settlement_cashcollection_history(
                $message ?: 'Cash collected from handyman',
                'cash_collection_by_provider',
                date('Y-m-d'),
                date('h:i:s'),
                $commission,
                $partnerId,
                $orderId,
                null,
                $adminCommissionPct,
                $amount,
                $commission
            );

            $db->transComplete();

            return [
                'error'   => false,
                'message' => labels(SUCCESSFULLY_COLLECTED_COMMISSION, 'Successfully collected commision'),
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
