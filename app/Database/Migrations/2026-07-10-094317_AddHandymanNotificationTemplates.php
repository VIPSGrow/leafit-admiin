<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHandymanNotificationTemplates extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $db->table('notification_templates')->insertBatch([
            [
                'event_key' => 'booking_assigned',
                'title' => 'New Booking Assigned',
                'body' => 'You have been assigned to booking #[[booking_id]] for [[provider_name]] on [[date_of_service]] [[service_time]].',
                'parameters' => json_encode(['booking_id', 'provider_name', 'date_of_service', 'service_time']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'event_key' => 'booking_unassigned',
                'title' => 'Booking Unassigned',
                'body' => 'You have been removed from booking #[[booking_id]] for [[provider_name]].',
                'parameters' => json_encode(['booking_id', 'provider_name']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'event_key' => 'booking_on_the_way',
                'title' => 'On The Way',
                'body' => 'Booking #[[booking_id]] status has been updated to On The Way.',
                'parameters' => json_encode(['booking_id']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'event_key' => 'booking_arrived',
                'title' => 'Arrived',
                'body' => 'Booking #[[booking_id]] status has been updated to Arrived.',
                'parameters' => json_encode(['booking_id']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $db->table('notification_templates')
            ->whereIn('event_key', ['booking_assigned', 'booking_unassigned', 'booking_on_the_way', 'booking_arrived'])
            ->delete();
    }
}
