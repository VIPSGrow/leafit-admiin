<?php

namespace App\Libraries;

use CILogViewer\CILogViewer;

class CustomLogViewer extends CILogViewer
{
    /**
     * Our custom view path
     */
    protected $customViewPath = 'App\Views\LogViewer\logs';
    
    /**
     * Override showLogs to use our custom view
     */
    public function showLogs()
    {
        $request = \Config\Services::request();
        
        // Handle file deletion
        if (!is_null($request->getGet("del"))) {
            $this->deleteFiles(base64_decode($request->getGet("del")));
            $uri = \Config\Services::request()->uri->getPath();
            return redirect()->to('/'.$uri);
        }
        
        // Handle file download
        $dlFile = $request->getGet("dl");
        if (!is_null($dlFile)) {
            // Get the log folder path from config
            $logFolderPath = config('CILogViewer')->logFolderPath ?? WRITEPATH . 'logs/';
            $filePath = $logFolderPath . basename(base64_decode($dlFile));
            
            if (file_exists($filePath)) {
                return $this->downloadFile($filePath);
            }
        }
        
        // Handle API requests
        if (!is_null($request->getGet("api"))) {
            return $this->processAPIRequests($request->getGet("api"));
        }
        
        // Get file name from request
        $fileName = $request->getGet("f");
        
        // Call parent method to get files and logs
        $files = $this->getFiles();
        $currentFile = null;
        $logs = [];
        
        // Process log file
        if (!is_null($fileName) && !empty($files)) {
            $logFolderPath = config('CILogViewer')->logFolderPath ?? WRITEPATH . 'logs/';
            $currentFile = $logFolderPath . basename(base64_decode($fileName));
        } else if (is_null($fileName) && !empty($files)) {
            $logFolderPath = config('CILogViewer')->logFolderPath ?? WRITEPATH . 'logs/';
            $currentFile = $logFolderPath . $files[0];
        }
        
        if (!is_null($currentFile) && file_exists($currentFile)) {
            $fileSize = filesize($currentFile);
            
            if (is_int($fileSize) && $fileSize > 52428800) { // 50MB
                $logs = null;
            } else {
                $logs = $this->processLogs($this->getLogs($currentFile));
            }
        }
        
        // Prepare view data
        $data['logs'] = $logs;
        $data['files'] = !empty($files) ? $files : [];
        $data['currentFile'] = !is_null($currentFile) ? basename($currentFile) : "";
        
        // Render using our custom view
        return view($this->customViewPath, $data);
    }
} 