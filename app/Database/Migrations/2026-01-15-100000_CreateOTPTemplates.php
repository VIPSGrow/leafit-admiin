<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOTPTemplates extends Migration
{
    /**
     * Create OTP email and SMS templates with default content
     * This migration creates base templates and default language translations
     */
    public function up()
    {
        $db = \Config\Database::connect();
        // Get default language code
        $defaultLang = $db->table('languages')
            ->where('is_default', 1)
            ->get()
            ->getRowArray();
        $defaultLangCode = !empty($defaultLang) ? $defaultLang['code'] : 'en';

        // Insert OTP Email Template
        $emailTemplateData = [
            'type' => 'send_otp',
            'subject' => 'Your OTP Code',
            'template' => '<html><body>
                <h2>Your OTP Code</h2>
                <p>Hello,</p>
                <p>Your OTP code is: <strong style="font-size: 24px; color: #007bff;">[[otp]]</strong></p>
                <p>Please do not share this code with anyone.</p>
                <p>This code will expire in a few minutes.</p>
                <p>Best regards,<br>[[company_name]]</p>
            </body></html>',
            'to' => '',
            'bcc' => null,
            'cc' => null,
            'parameters' => json_encode(['otp', 'company_name']),
        ];
        $db->table('email_templates')->insert($emailTemplateData);
        $emailTemplateId = $db->insertID();

        // Insert OTP SMS Template
        $smsTemplateData = [
            'type' => 'send_otp',
            'title' => 'OTP Code',
            'template' => 'Your OTP code is [[otp]]. Please do not share with anyone. Valid for a few minutes.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'parameters' => json_encode(['otp']),
        ];
        $db->table('sms_templates')->insert($smsTemplateData);
        $smsTemplateId = $db->insertID();
        // Insert default language translations for email template
        if ($emailTemplateId) {
            $emailTranslationData = [
                'template_id' => $emailTemplateId,
                'language_code' => $defaultLangCode,
                'subject' => 'Your OTP Code',
                'template' => '<html><body>
                    <h2>Your OTP Code</h2>
                    <p>Hello,</p>
                    <p>Your OTP code is: <strong style="font-size: 24px; color: #007bff;">[[otp]]</strong></p>
                    <p>Please do not share this code with anyone.</p>
                    <p>This code will expire in a few minutes.</p>
                    <p>Best regards,<br>[[company_name]]</p>
                </body></html>',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $db->table('translated_email_templates')->insert($emailTranslationData);
        }

        // Insert default language translations for SMS template
        if ($smsTemplateId) {
            $smsTranslationData = [
                'template_id' => $smsTemplateId,
                'language_code' => $defaultLangCode,
                'title' => 'OTP Code',
                'template' => 'Your OTP code is [[otp]]. Please do not share with anyone. Valid for a few minutes.',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $db->table('translated_sms_templates')->insert($smsTranslationData);
        }
    }

    /**
     * Remove OTP templates and their translations
     * This is the rollback operation
     */
    public function down()
    {
        $db = \Config\Database::connect();
        // Get template IDs
        $emailTemplate = $db->table('email_templates')
            ->where('type', 'send_otp')
            ->get()
            ->getRowArray();
        
        $smsTemplate = $db->table('sms_templates')
            ->where('type', 'send_otp')
            ->get()
            ->getRowArray();

        // Delete translations first (foreign key constraints)
        if (!empty($emailTemplate)) {
            $db->table('translated_email_templates')
                ->where('template_id', $emailTemplate['id'])
                ->delete();
        }

        if (!empty($smsTemplate)) {
            $db->table('translated_sms_templates')
                ->where('template_id', $smsTemplate['id'])
                ->delete();
        }

        // Delete base templates
        $db->table('email_templates')->where('type', 'send_otp')->delete();
        $db->table('sms_templates')->where('type', 'send_otp')->delete();
    }
}
