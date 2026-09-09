<?php

namespace Config;

use App\Jobs\DemoAutoResetJob;
use App\Jobs\Email;
use App\Jobs\FileManagerChangesJobs;
use App\Jobs\NumberLoggerJob;
use CodeIgniter\Queue\Config\Queue as BaseQueue;
use CodeIgniter\Queue\Drivers\DatabaseDriver;
use CodeIgniter\Queue\Exceptions\QueueException;

class Queue extends BaseQueue
{
    /**
     * Default queue driver to use
     */
    public string $default = 'database';
    public string $defaultHandler = 'database';

    public array $database = [
        'dbGroup'   => 'default',
        'getShared' => true,
        'skipLocked' => false,
    ];

    public bool $keepDoneJobs = false;

    public bool $keepFailedJobs = false;

    public array $queueDefaultPriority = [];

    public array $queuePriorities = [
        'emails' => ['high', 'low', 'default'],
        'notifications' => ['high', 'default', 'low'],
    ];
    
    public array $jobHandlers = [
        'email' => Email::class,
        'fileManagerChangesJob' => FileManagerChangesJobs::class,
        // Legacy unified handler — kept for backward compatibility.
        'sendNotification' => \App\Jobs\SendNotificationJob::class,
        'sendFcmNotification'      => \App\Jobs\SendFcmNotificationJob::class,
        'sendEmailSmsNotification' => \App\Jobs\SendEmailSmsNotificationJob::class,
        'demoAutoReset'            => DemoAutoResetJob::class,
    ];

    public function resolveJobClass(string $name): string
    {
        if (! isset($this->jobHandlers[$name])) {
            throw QueueException::forIncorrectJobHandler();
        }

        return $this->jobHandlers[$name];
    }

    /**
     * Stringify queue priorities.
     */
    public function getQueuePriorities(string $name): ?string
    {
        if (! isset($this->queuePriorities[$name])) {
            return null;
        }

        return implode(',', $this->queuePriorities[$name]);
    }
}
