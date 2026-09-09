<?php

namespace App\Controllers\Admin;

class LogViewerController extends Admin
{
    public $addresses, $validation, $uri;
    protected $logFolderPath = WRITEPATH . 'logs/';
    protected $logFilePattern = 'log-*.log';
    
    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
    }
    
    public function index()
    {

        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        
        $request = \Config\Services::request();
        
        // Handle file deletion
        if (!is_null($request->getGet("del"))) {
            $this->deleteFiles(base64_decode($request->getGet("del")));
            return redirect()->to(current_url());
        }
        
        // Handle file download
        $dlFile = $request->getGet("dl");
        if (!is_null($dlFile) && file_exists($this->logFolderPath . basename(base64_decode($dlFile)))) {
            $file = $this->logFolderPath . basename(base64_decode($dlFile));
            return $this->downloadFile($file);
        }
        
        // Get file name from request
        $fileName = $request->getGet("f");
        
        // Get log files
        $files = $this->getFiles();
        
        // Determine current file
        if (!is_null($fileName)) {
            $currentFile = $this->logFolderPath . basename(base64_decode($fileName));
        } elseif (is_null($fileName) && !empty($files)) {
            $currentFile = $this->logFolderPath . $files[0];
        } else {
            $currentFile = null;
        }
        
        // Process logs
        if (!is_null($currentFile) && file_exists($currentFile)) {
            $fileSize = filesize($currentFile);
            
            if (is_int($fileSize) && $fileSize > 52428800) { // 50MB
                $logs = null;
            } else {
                $logs = $this->processLogs($this->getLogs($currentFile));
            }
        } else {
            $logs = [];
        }
        
        // Prepare view data
        $data['logs'] = $logs;
        $data['files'] = !empty($files) ? $files : [];
        $data['currentFile'] = !is_null($currentFile) ? basename($currentFile) : "";
     
        // Render using our custom view
        return view('LogViewer/logs', $data);
    }
    
    /**
     * Get log files
     */
    private function getFiles()
    {
        $files = glob($this->logFolderPath . $this->logFilePattern);
        $files = array_reverse($files);
        $files = array_map('basename', $files);
        return $files;
    }
    
    /**
     * Get logs from file
     */
    // private function getLogs($file)
    // {
    //     try {
    //         $file = file_get_contents($file);
    //         $pattern = '/^([A-Z]+)\s*\-\s*([\-\d]+\s+[\:\d]+)\s*\-\->\s*(.+)$/m';
            
    //         preg_match_all($pattern, $file, $matches, PREG_SET_ORDER);
    //         return $matches;
    //     } catch (\Exception $e) {
    //         return [];
    //     }
    // }
    private function getLogs($file)
    {
        try {
            $file = file_get_contents($file);
            // This pattern captures the header line
            $pattern = '/^([A-Z]+)\s*\-\s*([\-\d]+\s+[\:\d]+)\s*\-\->\s*(.+)$/m';
            
            // Split the file by log entries (each entry starts with a level like ERROR)
            $entries = preg_split('/(^[A-Z]+\s*\-\s*[\-\d]+\s+[\:\d]+\s*\-\->)/m', $file, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
          
            $logs = [];
            for ($i = 0; $i < count($entries); $i += 2) {
                if (isset($entries[$i+1])) {
                    // Combine the header with content
                    $header = trim($entries[$i]);
                    $content = trim($entries[$i+1]);
                    
                    // Extract level and date from header
                    if (preg_match('/^([A-Z]+)\s*\-\s*([\-\d]+\s+[\:\d]+)\s*\-\->$/m', $header, $matches)) {
                        $level = $matches[1];
                        $date = $matches[2];
                        
                        // Remove the space between header and content
                        $logs[] = [$header . $content, $level, $date, $content];
                    }
                }
            }   
          
            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * Process logs
     */
    private function processLogs($logs)
    {
        if (is_null($logs)) {
            return null;
        }

        $logs = array_reverse($logs);

        $levelIcons = [
            'CRITICAL' => 'glyphicon glyphicon-warning-sign',
            'INFO' => 'glyphicon glyphicon-info-sign',
            'ERROR' => 'glyphicon glyphicon-warning-sign',
            'DEBUG' => 'glyphicon glyphicon-exclamation-sign',
            'NOTICE' => 'glyphicon glyphicon-info-sign',
            'WARNING' => 'glyphicon glyphicon-warning-sign',
            'EMERGENCY' => 'glyphicon glyphicon-warning-sign',
            'ALERT' => 'glyphicon glyphicon-warning-sign',
            'ALL' => 'glyphicon glyphicon-minus',
        ];
        
        $levelClasses = [
            'CRITICAL' => 'danger',
            'INFO' => 'info',
            'ERROR' => 'danger',
            'DEBUG' => 'warning',
            'NOTICE' => 'info',
            'WARNING' => 'warning',
            'EMERGENCY' => 'danger',
            'ALERT' => 'warning',
            'ALL' => 'muted',
        ];
        
        $result = [];
        
        foreach ($logs as $log) {
          
            $level = $log[1];
            $date = $log[2];
            $content = $log[3];
            
            // Get context and message
            $context = 'local'; // Default context
            
            // Create log entry
            $result[] = [
                'level' => $level,
                'date' => $date,
                'content' => $content,
                'context' => $context,
                'icon' => $levelIcons[$level] ?? 'glyphicon glyphicon-warning-sign',
                'class' => $levelClasses[$level] ?? 'info',
            ];
        }
       
        return $result;
    }
    
    /**
     * Download file
     */
    private function downloadFile($file)
    {
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    }
    
    /**
     * Delete files
     */
    private function deleteFiles($file)
    {
        if ($file === 'all') {
            // Delete all log files
            array_map('unlink', glob($this->logFolderPath . $this->logFilePattern));
        } else {
            // Delete single file
            $file = $this->logFolderPath . basename($file);
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
