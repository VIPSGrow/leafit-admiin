<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration to add context_data column to notifications table
 * 
 * This column stores additional context information as JSON for notifications.
 * It allows storing extra information like sender_id, receiver_id, booking_status, etc.
 * that might be needed for template rendering or future reference.
 */
class AddContextDataToNotificationsTable extends Migration
{
    public function up()
    {
        // Add context_data column to notifications table
        // This column stores additional context information as JSON
        // We use a try-catch to handle the case where the column might already exist
        try {
            $this->forge->addColumn('notifications', [
                'event_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['sent_by_admin', 'system_generated'],
                    'null' => false,
                    'default' => 'sent_by_admin',
                    'comment' => 'Event type that triggered this notification (sent_by_admin or system_generated)',
                    'after' => 'type'
                ],
                'context_data' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                    'comment' => 'JSON data containing additional context (sender_id, receiver_id, booking_status, etc.)',
                    'after' => 'url'
                ],
            ]);
        } catch (\Exception $e) {
            // Column might already exist, which is fine
            // Log the error but don't fail the migration
            log_message('info', 'Migration: context_data column may already exist in notifications table');
        }
    }

    public function down()
    {
        // Drop context_data column from notifications table
        // We use a try-catch to handle the case where the column might not exist
        try {
            $this->forge->dropColumn('notifications', 'context_data');
        } catch (\Exception $e) {
            // Column might not exist, which is fine
            // Log the error but don't fail the migration
            log_message('info', 'Migration: context_data column may not exist in notifications table');
        }
    }
}
