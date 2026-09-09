<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTranslatedChatQuestionsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('translated_chat_questions')) {
            $db->query("
                CREATE TABLE `translated_chat_questions` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `chat_question_id` INT(11) UNSIGNED NOT NULL COMMENT 'References chat_questions.id',
                    `language_code` VARCHAR(16) NOT NULL COMMENT 'References languages.code',
                    `question` VARCHAR(500) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_question_lang` (`chat_question_id`, `language_code`),
                    KEY `idx_language_code` (`language_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Translations for chat questions.'
            ");
        }

        if ($db->tableExists('translated_chat_questions') && $db->tableExists('chat_questions')) {
            try {
                $db->query("
                    ALTER TABLE `translated_chat_questions`
                    ADD CONSTRAINT `fk_tcq_question`
                    FOREIGN KEY (`chat_question_id`) REFERENCES `chat_questions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
            }
        }

        if ($db->tableExists('translated_chat_questions') && $db->tableExists('languages')) {
            try {
                $db->query("
                    ALTER TABLE `translated_chat_questions`
                    ADD CONSTRAINT `fk_tcq_language`
                    FOREIGN KEY (`language_code`) REFERENCES `languages`(`code`) ON DELETE CASCADE ON UPDATE CASCADE
                ");
            } catch (\Throwable $e) {
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('translated_chat_questions')) {
            try {
                $db->query("ALTER TABLE `translated_chat_questions` DROP FOREIGN KEY `fk_tcq_question`");
            } catch (\Throwable $e) {
            }
            try {
                $db->query("ALTER TABLE `translated_chat_questions` DROP FOREIGN KEY `fk_tcq_language`");
            } catch (\Throwable $e) {
            }
        }

        $db->query("DROP TABLE IF EXISTS `translated_chat_questions`");
    }
}
