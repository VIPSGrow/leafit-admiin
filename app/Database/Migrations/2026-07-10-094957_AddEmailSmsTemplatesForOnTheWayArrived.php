<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmailSmsTemplatesForOnTheWayArrived extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $params = json_encode(['booking_id', 'order_id', 'booking_status', 'provider_name', 'customer_name', 'date_of_service', 'service_time', 'company_name']);

        $db->table('email_templates')->insertBatch([
            [
                'type' => 'booking_on_the_way',
                'subject' => 'Booking On The Way - Booking #[[booking_id]]',
                'to' => '',
                'template' => '<p>Hello,</p><p>&nbsp;</p><p>Your booking #[[booking_id]] status has been updated to <strong>On The Way</strong>.</p><p>&nbsp;</p><p>Best regards,</p><p>[[company_name]] Team</p>',
                'bcc' => '',
                'cc' => '',
                'parameters' => $params,
            ],
            [
                'type' => 'booking_arrived',
                'subject' => 'Booking Arrived - Booking #[[booking_id]]',
                'to' => '',
                'template' => '<p>Hello,</p><p>&nbsp;</p><p>Your booking #[[booking_id]] status has been updated to <strong>Arrived</strong>.</p><p>&nbsp;</p><p>Best regards,</p><p>[[company_name]] Team</p>',
                'bcc' => '',
                'cc' => '',
                'parameters' => $params,
            ],
        ]);

        $db->table('sms_templates')->insertBatch([
            [
                'type' => 'booking_on_the_way',
                'title' => 'Booking On The Way',
                'template' => 'Your booking #[[booking_id]] status has been updated to On The Way.',
                'parameters' => $params,
            ],
            [
                'type' => 'booking_arrived',
                'title' => 'Booking Arrived',
                'template' => 'Your booking #[[booking_id]] status has been updated to Arrived.',
                'parameters' => $params,
            ],
        ]);
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->table('email_templates')->whereIn('type', ['booking_on_the_way', 'booking_arrived'])->delete();
        $db->table('sms_templates')->whereIn('type', ['booking_on_the_way', 'booking_arrived'])->delete();
    }
}
