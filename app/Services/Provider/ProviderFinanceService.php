<?php

namespace App\Services\Provider;

use App\Models\Payment_request_model;
use App\Models\Users_model;
use Config\Database;

/**
 * Owns provider finance workflows: withdrawal-request approval/rejection,
 * commission settlement, cash-collection deduction, and bulk variants.
 *
 * Phase 3 step 5B of the Provider refactor. CI4 Models + query builder
 * end-to-end; no `fetch_details` / `update_details` / `insert_details` calls.
 *
 * Validation stays in the controller — services consume already-validated input.
 *
 * Note: Settlement_model + Cash_collection_model declare `$table = 'cities'`
 * (pre-existing legacy quirk — outside this refactor's scope). Inserts into
 * `settlement_history` and `cash_collection` therefore use the query builder
 * with explicit table names, matching the previous helper-based path.
 */
class ProviderFinanceService
{
    private Users_model $users;
    private Payment_request_model $paymentRequests;

    public function __construct()
    {
        // labels / add_settlement_cashcollection_history / add_transaction / queue_notification_service
        helper('function');
        $this->users           = new Users_model();
        $this->paymentRequests = new Payment_request_model();
    }

    /**
     * Approve or disapprove a partner withdrawal/payment request.
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable
     */
    public function processPaymentRequest(
        int $requestId,
        int $userId,
        int $adminId,
        ?string $reason,
        $amount,
        int $status,
        string $defaultLanguage
    ): array {
        $partner = $this->users->find($userId);
        $admin   = $this->users->find($adminId);

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            if ($status === 1) {
                // Approve: credit the admin balance with the amount, mark request settled.
                if (empty($partner)) {
                    $db->transComplete();
                    return ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred')];
                }

                $this->paymentRequests->update($requestId, ['remarks' => $reason, 'status' => $status]);

                $updateBalance = (int) ($admin['balance'] ?? 0) + $amount;
                $updated       = $this->users->update($adminId, ['balance' => $updateBalance]);

                add_settlement_cashcollection_history(
                    $reason,
                    'settled_by_payment_request',
                    date('Y-m-d'),
                    date('H:i:s'),
                    $amount,
                    $userId,
                    '',
                    $requestId,
                    '',
                    $amount,
                    ''
                );

                if (!$updated) {
                    $db->transComplete();
                    return ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred')];
                }

                $this->notifyWithdrawDecision(
                    eventType: 'withdraw_request_approved',
                    statusKey: 'approve',
                    userId: $userId,
                    amount: $amount,
                    defaultLanguage: $defaultLanguage
                );

                $db->transComplete();
                return [
                    'error'   => false,
                    'message' => labels(DEBITED_AMOUNT, "debited amount $amount"),
                ];
            }

            // Disapprove: refund the partner's balance, mark request rejected.
            $updateBalance = (int) ($partner['balance'] ?? 0) + $amount;
            $updated       = $this->users->update($userId, ['balance' => $updateBalance]);
            $this->paymentRequests->update($requestId, ['remarks' => $reason, 'status' => $status]);

            if (!$updated) {
                $db->transComplete();
                return ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred')];
            }

            $this->notifyWithdrawDecision(
                eventType: 'withdraw_request_disapproved',
                statusKey: 'reject',
                userId: $userId,
                amount: $amount,
                defaultLanguage: $defaultLanguage
            );

            $db->transComplete();
            return [
                'error'   => false,
                'message' => labels(REJECTION_OCCURRED, 'Rejection occurred'),
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Settle commission payout: deduct from partner balance, record
     * settlement_history + transaction, notify partner.
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable
     */
    public function payOutCommission(
        int $partnerId,
        int $adminId,
        float $amount,
        ?string $message,
        string $defaultLanguage
    ): array {
        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            // Re-read balance under the open transaction.
            $current = $this->users->select(['balance'])->find($partnerId);
            if (empty($current) || $current['balance'] <= 0) {
                $db->transComplete();
                return [
                    'error'   => true,
                    'message' => labels(CANNOT_WITHDRAW_WHEN_BALANCE_IS_0_OR_LESS, 'Cannot withdraw when balance is 0 or less'),
                ];
            }
            if ($amount > $current['balance']) {
                $db->transComplete();
                return [
                    'error'   => true,
                    'message' => labels(AMOUNT_MUST_BE_LESS_THAN_OR_EQUAL_TO_CURRENT_BALANCE, 'Amount must be less than or equal to current balance'),
                ];
            }

            $updatedBalance = $current['balance'] - $amount;
            $balanceUpdate  = $this->users->update($partnerId, ['balance' => $updatedBalance]);

            $t = time();
            $transactionData = [
                'transaction_type' => 'transaction',
                'user_id'          => $adminId,
                'partner_id'       => $partnerId,
                'order_id'         => "TXN-$t",
                'type'             => 'fund_transfer',
                'txn_id'           => '',
                'amount'           => $amount,
                'status'           => 'success',
                'currency_code'    => null,
                'message'          => 'commission settled',
            ];
            $settlementHistory = [
                'provider_id' => $partnerId,
                'message'     => $message,
                'amount'      => $amount,
                'status'      => 'credit',
                'date'        => date('Y-m-d H:i:s'),
            ];

            $db->table('settlement_history')->insert($settlementHistory);
            add_settlement_cashcollection_history(
                'Settled By admin',
                'settled_by_settlement',
                date('d-m-t'),
                date('h:i'),
                $amount,
                $partnerId,
                '',
                '',
                '',
                $amount,
                ''
            );

            if (!$balanceUpdate) {
                $db->transComplete();
                return [
                    'error'   => true,
                    'message' => labels(UNSUCCESSFUL_WHILE_UPDATING_SETTLING_STATUS, 'Unsuccessful while Updating settling status'),
                ];
            }

            if (!add_transaction($transactionData)) {
                $db->transComplete();
                return [
                    'error'   => true,
                    'message' => labels(UNSUCCESSFUL_WHILE_ADDING_TRANSACTION, 'Unsuccessful while adding transaction'),
                ];
            }

            $this->notifyPaymentSettlement($partnerId, $amount, $defaultLanguage);

            $db->transComplete();
            return [
                'error'   => false,
                'message' => labels(COMMISSION_SETTLED_SUCCESSFULLY, 'Commission Settled Successfully'),
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Record an admin-collected cash payment against the partner's
     * payable_commision balance.
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable
     */
    public function deductCashCollection(int $partnerId, int $adminId, float $amount, string $message): array
    {
        $current = $this->users->select(['payable_commision'])->find($partnerId);
        if (empty($current)) {
            return ['error' => true, 'message' => labels(USER_NOT_FOUND, 'User not found')];
        }

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            $db->table('cash_collection')->insert([
                'user_id'    => $adminId,
                'message'    => $message,
                'status'     => 'admin_cash_recevied',
                'commison'   => (int) $amount,
                'partner_id' => $partnerId,
                'date'       => date('Y-m-d'),
            ]);

            $updatedBalance = $current['payable_commision'] - (int) $amount;
            $update         = $this->users->update($partnerId, ['payable_commision' => $updatedBalance]);

            add_settlement_cashcollection_history(
                $message,
                'cash_collection_by_admin',
                date('Y-m-d'),
                date('h:i:s'),
                $amount,
                $partnerId,
                '',
                '',
                '',
                $amount,
                ''
            );

            if (!$update) {
                $db->transComplete();
                return [
                    'error'   => true,
                    'message' => labels(UNSUCCESSFUL_WHILE_UPDATING_SETTING_STATUS, 'Unsuccessful while Updating settling status'),
                ];
            }

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

    /**
     * Bulk-settle commission for the given partner ids — zeroes `users.balance`
     * and writes a `settlement_history` row for each partner with a positive
     * balance. Returns success when at least one partner was settled.
     *
     * @param int[] $partnerIds
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable
     */
    public function bulkSettleCommission(array $partnerIds, ?string $message): array
    {
        if (empty($partnerIds)) {
            return ['error' => true, 'message' => labels('select_provider', 'Select Provider')];
        }

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();
        $anySettled = false;

        try {
            foreach ($partnerIds as $partnerId) {
                $partnerId = (int) $partnerId;
                $user      = $this->users->select(['balance'])->find($partnerId);
                if (empty($user) || $user['balance'] <= 0) {
                    continue;
                }

                $anySettled = true;
                $this->users->update($partnerId, ['balance' => 0]);
                $db->table('settlement_history')->insert([
                    'provider_id' => $partnerId,
                    'message'     => $message,
                    'amount'      => $user['balance'],
                    'status'      => 'credit',
                    'date'        => date('Y-m-d H:i:s'),
                ]);
            }

            $db->transComplete();

            return $anySettled
                ? ['error' => false, 'message' => labels(BULK_UPDATE_SUCCESSFULLY, 'Bulk update successfully')]
                : ['error' => true,  'message' => 'Cannot Update'];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Bulk admin-cash-receive against `users.payable_commision`.
     *
     * @param int[] $partnerIds
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable
     */
    public function bulkCollectCash(array $partnerIds, int $adminId, ?string $message): array
    {
        $db = Database::connect();
        $db->transException(true);
        $db->transStart();
        $anyCollected = false;

        try {
            foreach ($partnerIds as $partnerId) {
                $partnerId = (int) $partnerId;
                $user      = $this->users->select(['payable_commision'])->find($partnerId);
                if (empty($user) || $user['payable_commision'] <= 0) {
                    continue;
                }

                $anyCollected = true;
                $payable      = (int) $user['payable_commision'];
                $this->users->update($partnerId, ['payable_commision' => 0]);
                $db->table('cash_collection')->insert([
                    'user_id'    => $adminId,
                    'message'    => $message,
                    'status'     => 'admin_cash_recevied',
                    'commison'   => $payable,
                    'partner_id' => $partnerId,
                    'date'       => date('Y-m-d'),
                ]);
            }

            $db->transComplete();

            return $anyCollected
                ? ['error' => false, 'message' => labels(BULK_UPDATE_SUCCESSFULLY, 'Bulk update successfully')]
                : ['error' => true,  'message' => labels(CANNOT_UPDATE, 'Cannot Update')];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Queue FCM/email/SMS notification for withdrawal approval/rejection.
     * Failures are logged and swallowed — must not block the finance flow.
     */
    private function notifyWithdrawDecision(
        string $eventType,
        string $statusKey,
        int $userId,
        $amount,
        string $defaultLanguage
    ): void {
        try {
            queue_notification_service(
                eventType: $eventType,
                recipients: ['user_id' => $userId],
                context: [
                    'provider_id' => $userId,
                    'user_id'     => $userId,
                    'amount'      => $amount,
                ],
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $defaultLanguage,
                    'platforms' => ['android', 'ios', 'provider_panel'],
                    'type'      => 'withdraw_request',
                    'data'      => [
                        'status'       => $statusKey,
                        'type_id'      => (string) $userId,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[' . strtoupper($eventType) . '] Notification error trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Queue FCM/email/SMS notification for payment settlement.
     * Failures are logged and swallowed.
     */
    private function notifyPaymentSettlement(int $partnerId, $amount, string $defaultLanguage): void
    {
        try {
            queue_notification_service(
                eventType: 'payment_settlement',
                recipients: ['user_id' => $partnerId],
                context: [
                    'provider_id' => $partnerId,
                    'user_id'     => $partnerId,
                    'amount'      => $amount,
                ],
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $defaultLanguage,
                    'platforms' => ['android', 'ios', 'provider_panel'],
                    'type'      => 'settlement',
                    'data'      => [
                        'type_id'      => (string) $partnerId,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[PAYMENT_SETTLEMENT] Notification error trace: ' . $e->getTraceAsString());
        }
    }
}
