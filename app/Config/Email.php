<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use Exception;

class Email extends BaseConfig
{
    /**
     * @var string
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Dynamically load SMTP settings from DB / helper
     */
    public function loadDynamicSettings(): void
    {
        // Make sure the helper is loaded
        helper('function'); // function_helper.php

        try {
            // Your existing helper function that fetches settings
            $settings = get_settings('email_settings', true);

            $this->SMTPHost   = $settings['smtpHost']      ?? '';
            $this->SMTPUser   = $settings['smtpUsername']  ?? '';
            $this->SMTPPass   = $settings['smtpPassword']  ?? '';
            $this->SMTPPort   = (int)($settings['smtpPort'] ?? 587);
            $this->SMTPCrypto = $settings['smtpEncryption'] ?? 'tls';
        } catch (Exception $e) {
            // Optional: log error
            log_message('error', 'Email config dynamic load failed: ' . $e->getMessage());
        }
    }

    public $fromEmail  = '';

    /**
     * @var string
     */
    public $fromName = "eDemand";

    /**
     * @var string
     */
    public $recipients;

    /**
     * The "user agent"
     *
     * @var string
     */
    public $userAgent = 'CodeIgniter';

    /**
     * The mail sending protocol: mail, sendmail, smtp
     *
     * @var string
     */
    public $protocol = 'smtp';

    /**
     * The server path to Sendmail.
     *
     * @var string
     */
    public $mailPath = '/usr/sbin/sendmail';

    /**
     * SMTP Server Address
     *
     * @var string
     */
    public $SMTPHost;

    /**
     * SMTP Username
     *
     * @var string
     */
    public $SMTPUser;

    /**
     * SMTP Password
     *
     * @var string
     */
    public $SMTPPass;

    /**
     * SMTP Port
     *
     * @var integer
     */
    public $SMTPPort;

    /**
     * SMTP Timeout (in seconds)
     *
     * @var integer
     */
    public $SMTPTimeout = 5;

    /**
     * Enable persistent SMTP connections
     *
     * @var boolean
     */
    public $SMTPKeepAlive = false;

    /**
     * SMTP Encryption. Either tls or ssl
     *
     * @var string
     */
    public $SMTPCrypto;

    /**
     * Which SMTP authentication method to use: login, plain
     *
     * @var string
     */
    public $SMTPAuthMethod = 'login';

    /**
     * Enable word-wrap
     *
     * @var boolean
     */
    public $wordWrap = true;

    /**
     * Character count to wrap at
     *
     * @var integer
     */
    public $wrapChars = 76;

    /**
     * Type of mail, either 'text' or 'html'
     *
     * @var string
     */
    public $mailType = 'text';

    /**
     * Character set (utf-8, iso-8859-1, etc.)
     *
     * @var string
     */
    public $charset = 'UTF-8';

    /**
     * Whether to validate the email address
     *
     * @var boolean
     */
    public $validate = false;

    /**
     * Email Priority. 1 = highest. 5 = lowest. 3 = normal
     *
     * @var integer
     */
    public $priority = 3;

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     *
     * @var string
     */
    public $CRLF = "\r\n";

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     *
     * @var string
     */
    public $newline = "\r\n";

    /**
     * Enable BCC Batch Mode.
     *
     * @var boolean
     */
    public $BCCBatchMode = false;

    /**
     * Number of emails in each BCC batch
     *
     * @var integer
     */
    public $BCCBatchSize = 200;

    /**
     * Enable notify message from server
     *
     * @var boolean
     */
    public $DSN = false;
}
