<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function notification($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('notification');
        }

        return new \App\Services\NotificationService();
    }

    public static function fileService($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('fileService');
        }

        return new \App\Services\utility\FileService();
    }

    /**
     * Custom email service that auto-loads DB-based SMTP settings.
     */
    public static function email($config = null, bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('email', $config);
        }

        // Use your Email config class
        $emailConfig = $config ?? config('Email');

        // Load dynamic SMTP settings from DB/helper
        if (method_exists($emailConfig, 'loadDynamicSettings')) {
            $emailConfig->loadDynamicSettings();
        }

        // Create the CI4 Email instance with our config
        return new \CodeIgniter\Email\Email($emailConfig);
    }

    /**
     * Slot generation engine. Returns a shared SlotService wired with
     * ScheduleRepository (reads), SlotGenerator (math), and LockManager (writes).
     *
     * Usage: service('slot')->getAvailableSlots($partnerId, $date, $duration);
     */
    public static function slot($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('slot');
        }

        return new \App\Services\Slot\SlotService(
            new \App\Services\Slot\ScheduleRepository(),
            new \App\Services\Slot\SlotGenerator(),
            new \App\Services\Slot\LockManager()
        );
    }

    public static function providerHomeScreenService($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('providerHomeScreenService');
        }

        return new \App\Services\Provider\HomeScreenService();
    }
}
