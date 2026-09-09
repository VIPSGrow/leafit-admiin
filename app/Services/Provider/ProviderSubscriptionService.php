<?php

namespace App\Services\Provider;

use App\Models\Partner_subscription_model;
use App\Models\Partners_model;
use App\Models\Subscription_model;
use Config\Database;

/**
 * Owns the partner-subscription assign + cancel flows.
 *
 * Phase 3 step 5A of the Provider refactor. Consolidates the two
 * `assign_subscription_to_partner*` and `cancel_subscription_plan*` controller
 * pairs into a single transactional unit each. Behaviour preserved verbatim
 * except for the documented `tax_id` null-coalesce fix.
 *
 * Uses CI4 Models end-to-end; no `function_helper.php` data-helper calls.
 */
class ProviderSubscriptionService
{
    private Subscription_model $subscriptions;
    private Partner_subscription_model $partnerSubscriptions;
    private Partners_model $partners;

    public function __construct()
    {
        // labels() / queue_notification_service() etc. live in function_helper.
        helper('function');
        $this->subscriptions        = new Subscription_model();
        $this->partnerSubscriptions = new Partner_subscription_model();
        $this->partners             = new Partners_model();
    }

    /**
     * Assign a subscription to a partner. Deactivates any existing active row
     * (via hard delete to preserve legacy behaviour), inserts the new row,
     * updates `partner_details.admin_commission`, and queues a
     * `subscription_changed` notification.
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable On unexpected failure (caller logs + maps).
     */
    public function assign(int $partnerId, int $subscriptionId): array
    {
        $subscription = $this->subscriptions->find($subscriptionId);
        if (empty($subscription)) {
            return [
                'error'   => true,
                'message' => labels(DATA_NOT_FOUND, 'Data not found'),
            ];
        }

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            // Remove existing active row (legacy: hard delete, not status flip).
            $existingActive = $this->partnerSubscriptions
                ->where(['partner_id' => $partnerId, 'status' => 'active'])
                ->first();
            if (!empty($existingActive['id'])) {
                $this->partnerSubscriptions->delete((int) $existingActive['id']);
            }

            $price                = calculate_subscription_price((int) $subscription['id']);
            $purchaseDate         = date('Y-m-d');
            $subscriptionDuration = $subscription['duration'];
            if ($subscriptionDuration === 'unlimited') {
                $subscriptionDuration = 0;
            }
            $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

            $newRow = [
                'partner_id'            => $partnerId,
                'subscription_id'       => $subscriptionId,
                'is_payment'            => '1',
                'status'                => 'active',
                'purchase_date'         => $purchaseDate,
                'expiry_date'           => $expiryDate,
                'name'                  => $subscription['name'],
                'description'           => $subscription['description'],
                'duration'              => $subscription['duration'],
                'price'                 => $subscription['price'],
                'discount_price'        => $subscription['discount_price'],
                'publish'               => $subscription['publish'] ?? null,
                'order_type'            => $subscription['order_type'],
                'max_order_limit'       => $subscription['max_order_limit'],
                'service_type'          => $subscription['service_type'],
                'max_service_limit'     => $subscription['max_service_limit'],
                'tax_type'              => $subscription['tax_type'],
                // Fix: legacy `_from_edit_provider` path used bare $row['tax_id']
                // and inserted NULL when the column was absent. Unified to ?? 0.
                'tax_id'                => $subscription['tax_id'] ?? 0,
                'is_commision'          => $subscription['is_commision'],
                'commission_threshold'  => $subscription['commission_threshold'],
                'commission_percentage' => $subscription['commission_percentage'],
                'transaction_id'        => '0',
                'tax_percentage'        => $price[0]['tax_percentage'] ?? 0,
            ];

            $commission = ($subscription['is_commision'] === 'yes')
                ? $subscription['commission_percentage']
                : 0;

            $this->partners
                ->where('partner_id', $partnerId)
                ->set(['admin_commission' => $commission])
                ->update();

            $this->partnerSubscriptions->insert($newRow);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        if ($db->transStatus() === false) {
            return [
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
            ];
        }

        $this->notifySubscriptionChanged($partnerId, $subscriptionId, $subscription, $purchaseDate, $expiryDate);

        return [
            'error'   => false,
            'message' => labels(ASSIGNED_SUBSCRIPTION_SUCCESSFULLY, 'Assigned Subscription successfully'),
        ];
    }

    /**
     * Cancel a partner's active subscription. No-op when none active.
     * Queues a `subscription_removed` notification on cancellation.
     *
     * @return array{error: bool, message: string}
     *
     * @throws \Throwable On unexpected failure (caller logs + maps).
     */
    public function cancel(int $partnerId): array
    {
        $active = $this->partnerSubscriptions
            ->where(['partner_id' => $partnerId, 'status' => 'active'])
            ->first();

        if (!empty($active['id'])) {
            $subscriptionName = $active['name'] ?? 'Subscription';
            $subscriptionId   = $active['subscription_id'] ?? null;

            $this->notifySubscriptionRemoved($partnerId, $subscriptionId, $subscriptionName);

            $this->partnerSubscriptions->update((int) $active['id'], ['status' => 'deactive']);
        }

        return [
            'error'   => false,
            'message' => labels(SUBSCRIPTION_CANCELLED_SUCCESSFULLY, 'Subscription Cancelled Successfully'),
        ];
    }

    /**
     * Queue FCM/email/SMS notification for subscription change.
     * Failures are logged and swallowed — must not block the assign flow.
     */
    private function notifySubscriptionChanged(
        int $partnerId,
        int $subscriptionId,
        array $subscription,
        string $purchaseDate,
        string $expiryDate
    ): void {
        try {
            $context = [
                'provider_id'           => $partnerId,
                'subscription_id'       => $subscriptionId,
                'subscription_name'     => $subscription['name'],
                'subscription_price'    => ($subscription['discount_price'] > 0)
                    ? $subscription['discount_price']
                    : $subscription['price'],
                'subscription_duration' => ($subscription['duration'] === 'unlimited')
                    ? 'Unlimited'
                    : $subscription['duration'] . ' days',
                'purchase_date'         => $purchaseDate,
                'expiry_date'           => $expiryDate,
            ];

            queue_notification_service(
                eventType: 'subscription_changed',
                recipients: ['user_id' => $partnerId],
                context: $context,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => get_current_language_from_request(),
                    'platforms' => ['android', 'ios', 'provider_panel'],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[SUBSCRIPTION_CHANGED] Notification error trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Queue FCM/email/SMS notification for subscription removal.
     * Failures are logged and swallowed — must not block the cancel flow.
     */
    private function notifySubscriptionRemoved(int $partnerId, ?int $subscriptionId, string $subscriptionName): void
    {
        try {
            queue_notification_service(
                eventType: 'subscription_removed',
                recipients: ['user_id' => $partnerId],
                context: [
                    'provider_id'       => $partnerId,
                    'subscription_id'   => $subscriptionId,
                    'subscription_name' => $subscriptionName,
                ],
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => get_current_language_from_request(),
                    'platforms' => ['android', 'ios', 'provider_panel'],
                ]
            );
        } catch (\Throwable $e) {
            log_message('error', '[SUBSCRIPTION_REMOVED] Notification error trace: ' . $e->getTraceAsString());
        }
    }
}
