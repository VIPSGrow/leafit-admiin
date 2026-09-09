<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeTransactionMessages extends Migration
{
    private array $gateways = [
        'flutterwave',
        'paystack',
        'stripe',
        'razorpay',
        'paypal',
        'xendit',
        'cashfree',
    ];

    private array $map = [
        'txn_order_placed' => [
            'Order placed successfully',
            'Payment successful',
            'Done',
            'done',
            'order Transction added',
        ],
        'txn_payment_cancelled_by_customer' => [
            'payment cancelled by customer',
            'payment cancle by customer',
        ],
        'txn_booking_cancelled' => [
            'Booking is cancelled',
            'Order is cancelled',
        ],
        'txn_payment_failed' => [
            'Payment Failed',
        ],
        'txn_additional_charges' => [
            'payment for additional charges',
        ],
        'txn_subscription_success' => [
            'subscription successful',
            'subscription successfull',
        ],
        'txn_refund_processed' => [
            'stripe_refund',
            'razorpay_refund',
            'paystack_refund',
            'paypal_refund',
            'flutterwave_refund',
            'xendit_refund',
            'cashfree_refund',
        ],
        'txn_manual_refund' => [
            'manual_refund',
            'manually_refund',
        ],
    ];

    public function up()
    {
        $builder = $this->db->table('transactions');

        foreach ($this->map as $key => $rawValues) {
            $builder
                ->whereIn('type', $this->gateways)
                ->whereIn('message', $rawValues)
                ->update(['message' => $key]);
        }
    }

    public function down()
    {
        // Reverse mapping uses the first raw value of each key as the restoration value.
        $reverse = [];
        foreach ($this->map as $key => $rawValues) {
            $reverse[$key] = $rawValues[0];
        }

        $builder = $this->db->table('transactions');

        foreach ($reverse as $key => $raw) {
            $builder
                ->whereIn('type', $this->gateways)
                ->where('message', $key)
                ->update(['message' => $raw]);
        }
    }
}
