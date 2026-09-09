<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedDefaultChatQuestions extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Only seed if the table is empty (fresh install or no questions yet)
        $existing = $db->table('chat_questions')->countAll();
        if ($existing > 0) {
            return;
        }

        $questions = [
            // Pre-Booking Questions
            ['type' => 'pre_booking', 'question' => 'Are you licensed and insured?', 'sort_order' => 0],
            ['type' => 'pre_booking', 'question' => 'How long does the service usually take?', 'sort_order' => 1],
            ['type' => 'pre_booking', 'question' => 'Do you provide a satisfaction guarantee?', 'sort_order' => 2],
            ['type' => 'pre_booking', 'question' => 'Do you bring your own tools and supplies?', 'sort_order' => 3],
            ['type' => 'pre_booking', 'question' => 'Do you offer any discounts or promotions?', 'sort_order' => 4],

            // Customer Post-Booking Questions
            ['type' => 'customer_post_booking', 'question' => 'Hello there!', 'sort_order' => 0],
            ['type' => 'customer_post_booking', 'question' => 'Hello there! Need to discuss a few things about the service.', 'sort_order' => 1],
            ['type' => 'customer_post_booking', 'question' => 'Hi there! Just a quick check-in before our scheduled service.', 'sort_order' => 2],
            ['type' => 'customer_post_booking', 'question' => 'Hello! I have a question regarding the service I booked.', 'sort_order' => 3],
            ['type' => 'customer_post_booking', 'question' => 'Hey! Confirming details for our upcoming service.', 'sort_order' => 4],
            ['type' => 'customer_post_booking', 'question' => 'Hi! Looking forward to our appointment. Anything I should know?🤔', 'sort_order' => 5],

            // Provider Post-Booking Questions
            ['type' => 'provider_post_booking', 'question' => 'Hello there!', 'sort_order' => 0],
            ['type' => 'provider_post_booking', 'question' => 'Hello there! Need to discuss a few things about the service.', 'sort_order' => 1],
            ['type' => 'provider_post_booking', 'question' => 'Hi there! Just a quick check-in before our scheduled service.', 'sort_order' => 2],
            ['type' => 'provider_post_booking', 'question' => 'Hello! I have a question regarding the service You have booked.', 'sort_order' => 3],
            ['type' => 'provider_post_booking', 'question' => 'Hey! Confirming details for our upcoming service.', 'sort_order' => 4],
            ['type' => 'provider_post_booking', 'question' => 'Hi! Looking forward to our appointment. Is there any additional information you\'d like to share? 🤔', 'sort_order' => 5],

            // Customer Admin Support Questions
            ['type' => 'customer_admin_support', 'question' => 'Hi there! Need help with my booking. Can you assist?', 'sort_order' => 0],
            ['type' => 'customer_admin_support', 'question' => 'Hello! Looking for a reliable provider. Any help?', 'sort_order' => 1],
            ['type' => 'customer_admin_support', 'question' => 'Hey! Question about service availability in my area. Any info?', 'sort_order' => 2],
            ['type' => 'customer_admin_support', 'question' => 'Hi! Issue with provider during service. Can you advise?', 'sort_order' => 3],
            ['type' => 'customer_admin_support', 'question' => 'Hello! Need to change my booking details. How do I do that?', 'sort_order' => 4],
            ['type' => 'customer_admin_support', 'question' => 'Hey! Question about the payment process. Can you clarify?', 'sort_order' => 5],

            // Provider Admin Support Questions
            ['type' => 'provider_admin_support', 'question' => "Hello Admin! I'm encountering an issue and could use your assistance. Can we discuss it?", 'sort_order' => 0],
            ['type' => 'provider_admin_support', 'question' => 'Hi there! Need to address an unexpected situation. Can you spare a moment to chat?', 'sort_order' => 1],
            ['type' => 'provider_admin_support', 'question' => 'Hello! I have some concerns that I believe require your attention. Can we connect?', 'sort_order' => 2],
            ['type' => 'provider_admin_support', 'question' => 'Hi Admin! Something has come up, and I need your guidance. Are you available to talk?', 'sort_order' => 3],
            ['type' => 'provider_admin_support', 'question' => "Hello! I'd like to report an incident and discuss the next steps. Can we have a conversation?", 'sort_order' => 4],
            ['type' => 'provider_admin_support', 'question' => 'Hi Admin! Seeking your support to resolve a problem. When can we discuss it?', 'sort_order' => 5],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($questions as &$q) {
            $q['created_at'] = $now;
            $q['updated_at'] = $now;
        }

        $db->table('chat_questions')->insertBatch($questions);
    }

    public function down()
    {
        // No rollback — questions may have been modified by admin
    }
}
