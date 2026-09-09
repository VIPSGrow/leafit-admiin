<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddForeignKeyToOtpsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('otps')) {
            return;
        }

        $this->db->query("ALTER TABLE `otps` ENGINE=InnoDB");

        $this->db->query("
            ALTER TABLE `otps`
            MODIFY `user_id` INT(11) UNSIGNED NULL
        ");

        $this->db->query("
            UPDATE `otps`
            SET `user_id` = NULL
            WHERE `user_id` IS NOT NULL
            AND `user_id` NOT IN (SELECT id FROM users)
        ");

        try {
            $this->db->query("
                ALTER TABLE `otps`
                DROP FOREIGN KEY `otps_user_id_fk`
            ");
        } catch (\Throwable $e) {
           
        }

        $this->db->query("
            ALTER TABLE `otps`
            ADD CONSTRAINT `otps_user_id_fk`
            FOREIGN KEY (`user_id`)
            REFERENCES `users`(`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ");
    }

    public function down()
    {
        try {
            $this->db->query("
                ALTER TABLE `otps`
                DROP FOREIGN KEY `otps_user_id_fk`
            ");
        } catch (\Throwable $e) {
        }
    }
}
