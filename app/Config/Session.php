<?php namespace Config;

use CodeIgniter\Config\BaseConfig;

class Session extends BaseConfig
{
    public $driver = 'CodeIgniter\Session\Handlers\FileHandler';
    public $cookieName = 'ci_session';
    public $expiration = 7200;
    public $savePath = WRITEPATH . 'session';
    public $matchIP = false;
    public $timeToUpdate = 300;
    public $regenerateDestroy = false;

    /**
     * DB Group for the database session.
     */
    public ?string $DBGroup = null;

    /**
     * Time (microseconds) to wait if lock cannot be acquired.
     * The default is 100,000 microseconds (= 0.1 seconds).
     */
    public int $lockRetryInterval = 100_000;

    /**
     * Maximum number of lock acquisition attempts.
     * The default is 300 times. That is lock timeout is about 30 (0.1 * 300)
     * seconds.
     */
    public int $lockMaxRetries = 300;
}