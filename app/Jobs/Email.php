<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;
use Exception;

class Email extends BaseJob implements JobInterface
{
    public function process()
    {
        log_message('info', 'Email job started.');

        try {
            $db = db_connect();
            $db->transStrict(false);
            $db->transBegin();

            // Get email settings
            $email_settings = get_settings('email_settings', true);

            // Initialize email service with proper configuration
            $config = [
                'protocol' => 'smtp',
                'SMTPHost' => $email_settings['smtpHost'] ?? '',
                'SMTPPort' => intval($email_settings['smtpPort']) ?? 587,
                'SMTPUser' => $email_settings['smtpUsername'] ?? '',
                'SMTPPass' => $email_settings['smtpPassword'] ?? '',
                'SMTPCrypto' => 'tls',
                'mailType' => 'html',
                'charset' => 'utf-8',
                'newline' => "\r\n"
            ];

            $email = \Config\Services::email($config);

            // Set email parameters from job data
            $email->setTo($this->data['to']);
            $email->setFrom($this->data['from_email'], $this->data['from_name']);
            $email->setSubject($this->data['subject']);
            $email->setMessage($this->data['message']);
            $email->setMailType('html');

            // Set optional parameters if provided
            if (!empty($this->data['bcc'])) {
                $email->setBCC($this->data['bcc']);
            }
            if (!empty($this->data['cc'])) {
                $email->setCC($this->data['cc']);
            }

            $result = $email->send(false);

            if (!$result) {
                throw new Exception($email->printDebugger('headers'));
            }

            log_message('info', 'Email sent successfully to: ' . $this->data['to']);

            if ($db->transStatus() === false) {
                $db->transRollback();
            } else {
                $db->transCommit();
            }

            return $result;
        } catch (\Throwable $th) {
            log_message('error', 'Error processing email job: ' . $th->getMessage());
            $db->transRollback();
            throw $th;
        }
    }
}
