<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class CILogViewer extends BaseConfig
{
    /**
     * View file for displaying logs
     * Using our custom view in app/Views/LogViewer folder
     */
    public $viewName = 'App\Views\LogViewer\logs';
    
    /**
     * Log folder path
     * Default is WRITEPATH . 'logs/'
     */
    public $logFolderPath = WRITEPATH . 'logs/';
    
    /**
     * Pattern to match log files
     */
    public $logFilePattern = 'log-*.log';
} 