<?php

namespace App\Controllers\Admin;

class Migrate extends Admin
{
    public $migrate;

    public function __construct()
    {
        $this->migrate =  \Config\Services::migrations();
    }

    public function index()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            $migrationsPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
            if (!$this->is_dir_empty($migrationsPath)) {
                try {
                    echo "<pre>";
                    print_r($this->migrate->findMigrations());
                    $this->migrate->latest();
                } catch (\Throwable $e) {
                    log_message('error', 'Migration Error: ' . $e->getMessage());
                    echo 'Migration Error';
                }
            }
        } else {
            echo labels('unauthorized_to_access_this_part', 'Unauthorized to access this part');
        }
    }

    public function rollback(int $version = 0)
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            $migrationsPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
            if (!$this->is_dir_empty($migrationsPath)) {
                try {
                    if (!empty($version) && is_numeric($version)) {
                        $this->migrate->regress($version);
                    } else {
                        echo labels('version_not_specified', 'Version not specified');
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Rollback Error: ' . $e->getMessage());
                    echo 'Rollback Error';
                }
            }
        } else {
            echo labels('unauthorized_to_access_this_part', 'Unauthorized to access this part');
        }
    }

    public function is_dir_empty($dir)
    {
        if (!is_readable($dir)) return NULL;
        return (count(scandir($dir)) == 2);
    }
}
