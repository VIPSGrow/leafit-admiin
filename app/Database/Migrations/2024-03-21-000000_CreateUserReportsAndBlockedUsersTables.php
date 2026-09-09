<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserReportsAndBlockedUsersTables extends Migration
{
    public function up()
    {
        // Create user_reports table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'reporter_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'reported_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'reason_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'additional_info' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('reporter_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reported_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reason_id', 'reasons_for_report_and_block_chat', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_reports');

        // Create blocked_users table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'blocked_user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('blocked_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('blocked_users');
    }

    public function down()
    {
        $this->forge->dropTable('user_reports');
        $this->forge->dropTable('blocked_users');
    }
} 