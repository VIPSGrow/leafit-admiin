<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBookingEndedStatusToBookingHandymen extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE `booking_handymen` MODIFY `status` ENUM('assigned','accepted','rejected','on_the_way','arrived','booking_ended','started','completed') NOT NULL DEFAULT 'assigned'");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE `booking_handymen` MODIFY `status` ENUM('assigned','accepted','rejected','on_the_way','arrived','started','completed') NOT NULL DEFAULT 'assigned'");
    }
}
