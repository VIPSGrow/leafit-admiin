<?php

namespace App\Controllers\Admin;

use Config\Database;

class DatabaseOperations extends Admin
{
    protected $superadmin, $validation, $db;

    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        helper('ResponceServices');
        helper('api');
        $this->superadmin = $this->session->get('email');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $_SESSION['toastMessage'] = labels($result['message'], $result['message']);
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/dashboard')->withCookies();
        }

        try {
            $db = Database::connect();
            $tables = $db->listTables();

            // Check if database has tables to backup
            if (empty($tables)) {
                $_SESSION['toastMessage'] = labels('no_tables_found', 'No tables found in the database to backup');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/dashboard')->withCookies();
            }

            // Create backup file name with timestamp
            $backupFileName = 'database_backup_' . date("Y-m-d-H-i-s") . '.sql';
            $backupFile = WRITEPATH . 'backups/' . $backupFileName;

            // Create backups directory if it doesn't exist
            if (!is_dir(WRITEPATH . 'backups')) {
                mkdir(WRITEPATH . 'backups', 0777, true);
            }

            // Open file handle for writing backup content
            $handle = fopen($backupFile, 'w+');
            if (!$handle) {
                $_SESSION['toastMessage'] = labels('backup_file_creation_failed', 'Failed to create backup file');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/dashboard')->withCookies();
            }

            // Generate backup content for each table
            foreach ($tables as $table) {
                // Write table structure comment
                fwrite($handle, "/* Table structure for table `$table` */\n\n");

                // Get and write CREATE TABLE statement (escape table name with backticks)
                $query = $db->query("SHOW CREATE TABLE `$table`;");
                $row = $query->getRowArray();
                $createTable = array_values($row)[1] . ";\n\n";
                fwrite($handle, $createTable);

                // Write data insertion comment
                fwrite($handle, "/* Dumping data for table `$table` */\n\n");

                // Get all data from table and create INSERT statements
                $query = $db->table($table)->get();
                foreach ($query->getResultArray() as $row) {
                    $keys = array_keys($row);
                    $values = array_map(function ($value) use ($db) {
                        return $db->escape($value);
                    }, array_values($row));
                    $sql = "INSERT INTO `$table` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    fwrite($handle, $sql);
                }
                fwrite($handle, "\n\n");
            }

            // Close file handle
            fclose($handle);

            // Check if backup file was created successfully
            if (file_exists($backupFile)) {
                // Use CodeIgniter's response download method (preferred approach)
                return $this->response->download($backupFile, null)->setFileName($backupFileName);
            } else {
                $_SESSION['toastMessage'] = labels('backup_file_not_created', 'Backup file was not created successfully');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/dashboard')->withCookies();
            }
        } catch (\Throwable $th) {
            // Log the error for debugging
            log_the_responce($th, 'DatabaseOperations ---> index()', 'error');

            // Set error message and redirect
            $_SESSION['toastMessage'] = labels('backup_creation_error', 'Error occurred while creating database backup');
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/dashboard')->withCookies();
        }
    }
    public function clean_database_index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('clean_database', 'Clean Database') . ' | ' . labels('admin_panel', 'Admin Panel'), 'clean_database');
        $db = db_connect();
        $tables = $db->listTables();
        $tableInfo = [];
        foreach ($tables as $table) {
            if ($table == 'settings' || $table == 'updates' || $table == "user_permissions" ||  $table == "migrations"  || $table == "groups" || $table == "email_templates" || $table == "languages") {
                continue;
            }
            $query = $db->query("SELECT COUNT(*) AS total FROM `$table`");
            $result = $query->getRow();
            $totalRecords = $result->total;
            $tableInfo[] = [
                'table' => $table,
                'total_records' => $totalRecords
            ];
        }
        $this->data['tables'] = $tableInfo;
        return view('backend/admin/template', $this->data);
    }
    public function  clean_database_tables()
    {
        $request = service('request');
        $tables_to_clean = $request->getPost('tables_to_clean');
        $db = \Config\Database::connect();
        if (!empty($tables_to_clean) && is_array($tables_to_clean)) {
            foreach ($tables_to_clean as $table_name) {
                if ($table_name == "addresses") {
                    $requiredTables = ["order_services", "orders"];
                    if (count(array_intersect($requiredTables, $tables_to_clean)) !== count($requiredTables)) {
                        return ErrorResponse("address table is used in the following tables, so you must clean them as well: " . json_encode($requiredTables), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if ($table_name == "partner_details") {
                    $requiredTables = ["partner_timings", "payment_request", "services", "settlement_history"];
                    if (count(array_intersect($requiredTables, $tables_to_clean)) !== count($requiredTables)) {
                        return ErrorResponse("partner_details table is used in the following tables, so you must clean them as well: " . json_encode($requiredTables), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                $db->table($table_name)->truncate();
            }
            return successResponse("Table truncate successfully", false, [], [], 200, csrf_token(), csrf_hash());
        } else {
            return ErrorResponse("No table were selected", true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function trigger_demo_reset()
    {
        $request = service('request');
        $isAjax  = $request->isAJAX() || strpos((string) $request->getHeaderLine('Accept'), 'application/json') !== false;

        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            if ($isAjax) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error'   => true,
                    'message' => 'Unauthorized',
                ]);
            }
            return redirect('admin/login');
        }

        if ($this->superadmin !== 'superadmin@gmail.com') {
            if ($isAjax) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error'   => true,
                    'message' => 'Forbidden',
                ]);
            }
            return redirect()->to('admin/dashboard')->withCookies();
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            if (!defined('STDIN'))  define('STDIN',  fopen('php://stdin',  'rb'));
            if (!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'wb'));
            if (!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));
            command('demo:reset');
            $message = labels('demo_reset_triggered_successfully', 'Demo reset triggered successfully');
            $_SESSION['toastMessage'] = $message;
            $_SESSION['toastMessageType'] = 'success';

            if ($isAjax) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => $message,
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', '[DEMO_RESET] trigger_demo_reset failed: ' . $e->getMessage());
            $message = labels('demo_reset_trigger_failed', 'Failed to trigger demo reset');
            $_SESSION['toastMessage'] = $message;
            $_SESSION['toastMessageType'] = 'error';

            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error'   => true,
                    'message' => $message,
                ]);
            }
        }

        $this->session->markAsFlashdata('toastMessage');
        $this->session->markAsFlashdata('toastMessageType');
        return redirect()->to('admin/dashboard')->withCookies();
    }
}
