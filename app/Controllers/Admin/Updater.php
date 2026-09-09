<?php
namespace App\Controllers\Admin;

use ZipArchive;
class Updater extends Admin
{
    public function index()
    {
        $db = \Config\Database::connect();
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('Updater', 'Updater') . ' | ' . labels('admin_panel', 'Admin Panel'), 'updater');
            $db = \Config\Database::connect();
            $this->data['version'] = $db->table('updates')->select('*')->orderBy('id', 'DESC')->get(1)->getResult();
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }
    public function is_dir_empty($dir)
    {
        if (!is_readable($dir))
            return NULL;
        return (count(scandir($dir)) == 2);
    }
    /**
     * Recursively copy files and folders from source to destination.
     * This is used for the "simple zip" update format where the archive
     * already mirrors the project structure (for example `app/`, `public/`, etc.).
     */
    private function recursive_copy($source, $destination)
    {
        if (is_dir($source)) {
            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }

            $items = scandir($source);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $srcPath = rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
                $dstPath = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;

                $this->recursive_copy($srcPath, $dstPath);
            }
        } elseif (is_file($source)) {
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0777, true);
            }
            copy($source, $destination);
        }
    }

    public function upload_update_file()
    {
        $security = \Config\Services::security();

        if ($this->isLoggedIn && $this->userIsAdmin) {
            // Demo mode guard:
            // - Block modifications in demo mode for all users
            // - Allow superadmin (superadmin@gmail.com) to perform updates
            $sessionEmail = isset($_SESSION['email']) ? (string) $_SESSION['email'] : '';
            $isSuperAdmin = (strtolower(trim($sessionEmail)) === 'superadmin@gmail.com');
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && !$isSuperAdmin) {
                $response['error'] = true;
                $response['message'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                $response['csrfName'] = $security->getTokenName();
                $response['csrfHash'] = $security->getHash();
                return $this->response->setJSON($response);
            }
            $db = \Config\Database::connect();
            $purchase_code = $this->request->getPost('purchase_code');
            $quiz_url = $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://validator.wrteam.in/edemand_validator?purchase_code=' . $purchase_code . '&domain_url=' . $quiz_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            $response = curl_exec($curl);
            $response = json_decode($response, 1);
            unset($curl);
            if ($response["error"] == false) {
                helper('filesystem');
                $db = \Config\Database::connect();
                if (!empty($_FILES['update_file']['name'][0])) {
                    if (!file_exists(FCPATH . UPDATE_PATH)) {
                        mkdir(FCPATH . UPDATE_PATH, 0777, true);
                    }
                    $uploadData = $this->request->getFile('update_file.0');
                    $ext = trim(strtolower($uploadData->getClientExtension()));
                    if ($ext != "zip") {
                        $response = [
                            "error" => true,
                            "message" => labels('please_insert_a_valid_zip_file', 'Please insert a valid zip file') . ".",
                            'data' => [],
                            $response['csrfName'] = $security->getTokenName(),
                            $response['csrfHash'] = $security->getHash(),
                        ];
                        return $this->response->setJSON($response);
                        die();
                    }
                    if ($uploadData->move(FCPATH . UPDATE_PATH)) {
                        $filename = $uploadData->getName();
                        ## Extract the zip file ---- start
                        $zip = new ZipArchive();
                        $res = $zip->open(FCPATH . UPDATE_PATH . $filename);
                        if ($res === TRUE) {
                            // Unzip path
                            $extractpath = FCPATH . UPDATE_PATH;
                            // Extract file
                            $zip->extractTo($extractpath);
                            $zip->close();
                            unlink(FCPATH . UPDATE_PATH . $filename);

                            /**
                             * Simple update format only:
                             * - A single zip that already contains the correct folder structure.
                             * - Example: `app/Controllers/...`, `app/Models/...`, `public/...`, `.env`, etc.
                             * We no longer support package.json / folders.json / files.json / archives.json.
                             * We simply copy everything from UPDATE_PATH into the project root and
                             * then run CI4 migrations if present.
                             */
                            $sourceRoot = rtrim(FCPATH . UPDATE_PATH, DIRECTORY_SEPARATOR);
                            /**
                             * Update manifest (required)
                             * ------------------------
                             * The uploaded update zip MUST contain a JSON file at the root:
                             * - `update.json`
                             *
                             * Required keys:
                             * - `current_version`: the version this update expects to be installed currently
                             * - `to_version`: the version the system will be updated to
                             *
                             * Why:
                             * - Helps prevent applying the wrong update file.
                             * - Lets us block re-applying an update that is already installed.
                             */
                            $manifestPath = $sourceRoot . DIRECTORY_SEPARATOR . 'update.json';
                            if (!is_file($manifestPath)) {
                                $response['error'] = true;
                                $response['message'] = labels('invalid_update_file_update_json_missing', 'Invalid update file: update.json missing') . '.';
                                // Delete extracted files (including hidden dotfiles like .DS_Store)
                                delete_files(FCPATH . UPDATE_PATH, true, false, true);
                                $response['csrfName'] = $security->getTokenName();
                                $response['csrfHash'] = $security->getHash();
                                return $this->response->setJSON($response);
                            }

                            $manifestRaw = @file_get_contents($manifestPath);
                            $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
                            $manifestCurrent = is_array($manifest) && isset($manifest['current_version']) ? trim((string) $manifest['current_version']) : '';
                            $manifestTo = is_array($manifest) && isset($manifest['to_version']) ? trim((string) $manifest['to_version']) : '';

                            if ($manifestCurrent === '' || $manifestTo === '') {
                                $response['error'] = true;
                                $response['message'] = labels('invalid_update_file_update_json_is_missing_required_keys', 'Invalid update file: update.json is missing required keys') . '.';
                                // Delete extracted files (including hidden dotfiles like .DS_Store)
                                delete_files(FCPATH . UPDATE_PATH, true, false, true);
                                $response['csrfName'] = $security->getTokenName();
                                $response['csrfHash'] = $security->getHash();
                                return $this->response->setJSON($response);
                            }

                            // Check latest installed version from DB (updates table last entry).
                            $latestRow = $db->table('updates')->select('version')->orderBy('id', 'DESC')->get(1)->getRowArray();
                            $latestVersion = (!empty($latestRow) && isset($latestRow['version'])) ? trim((string) $latestRow['version']) : '';

                            if ($latestVersion !== '') {
                                // Block downgrade: to_version must not be less than installed version.
                                if (version_compare($manifestTo, $latestVersion, '<')) {
                                    $response['error'] = true;
                                    $response['message'] = labels('downgrade_not_allowed', 'Downgrade is not allowed. Installed version is') . ' ' . $latestVersion . ', ' . labels('update_targets_version', 'update targets version') . ' ' . $manifestTo . '.';
                                    delete_files(FCPATH . UPDATE_PATH, true, false, true);
                                    $response['csrfName'] = $security->getTokenName();
                                    $response['csrfHash'] = $security->getHash();
                                    return $this->response->setJSON($response);
                                }

                                // Block non-sequential upgrade: update.json current_version must match installed version.
                                if ($manifestCurrent !== $latestVersion) {
                                    $response['error'] = true;
                                    $response['message'] = labels('update_version_mismatch', 'This update is for version') . ' ' . $manifestCurrent . ' ' . labels('but_installed_version_is', 'but installed version is') . ' ' . $latestVersion . '.';
                                    delete_files(FCPATH . UPDATE_PATH, true, false, true);
                                    $response['csrfName'] = $security->getTokenName();
                                    $response['csrfHash'] = $security->getHash();
                                    return $this->response->setJSON($response);
                                }
                            }

                            $items = is_dir($sourceRoot) ? scandir($sourceRoot) : [];

                            if (!empty($items)) {
                                foreach ($items as $item) {
                                    if ($item === '.' || $item === '..') {
                                        continue;
                                    }
                                    // Do not copy the update manifest file into the project root.
                                    if ($item === 'update.json') {
                                        continue;
                                    }

                                    $srcPath = $sourceRoot . DIRECTORY_SEPARATOR . $item;
                                    $dstPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;

                                    $this->recursive_copy($srcPath, $dstPath);
                                }

                                // Run migrations after files are copied.
                                // `latest()` safely no-ops when there are no pending migrations.
                                $migrationError = null;
                                try {
                                    $migrate = \Config\Services::migrations();
                                    $migrate->latest();
                                } catch (\Throwable $e) {
                                    $migrationError = $e->getMessage();
                                    log_message(
                                        'error',
                                        '[UPDATER] Simple ZIP Database Migration Error in {file} on line {line}: {message}',
                                        [
                                            'file' => $e->getFile(),
                                            'line' => $e->getLine(),
                                            'message' => $e->getMessage(),
                                        ]
                                    );
                                }

                                /**
                                 * Record successful update in DB.
                                 * Insert the target version into `updates.version`.
                                 *
                                 * We keep this tolerant to different schemas by only filling
                                 * fields that exist in the table (timestamp column names vary).
                                 */
                                try {
                                    $fields = $db->getFieldNames('updates');
                                    $insertData = [];

                                    if (is_array($fields)) {
                                        if (in_array('version', $fields, true)) {
                                            $insertData['version'] = $manifestTo;
                                        }
                                    }

                                    if (!empty($insertData)) {
                                        $db->table('updates')->insert($insertData);
                                    }
                                } catch (\Throwable $e) {
                                    log_message(
                                        'error',
                                        '[UPDATER] Failed to insert updates table row after update: {message}',
                                        ['message' => $e->getMessage()]
                                    );
                                }

                                // Bump APP_VERSION in .env so panel asset cache busting
                                // (get_system_version() query param) reflects the new version.
                                try {
                                    updateEnv('APP_VERSION', $manifestTo);
                                } catch (\Throwable $e) {
                                    log_message(
                                        'error',
                                        '[UPDATER] Failed to update APP_VERSION in .env: {message}',
                                        ['message' => $e->getMessage()]
                                    );
                                }

                                // Delete extracted files (including hidden dotfiles like .DS_Store)
                                delete_files(FCPATH . UPDATE_PATH, true, false, true);

                                $response['error'] = false;
                                $successMsg = labels('Congratulations!', 'Congratulations!') . ' ' .
                                    labels('the_system_has_been_updated_from', 'The system has been updated from') . ' ' .
                                    $manifestCurrent . ' ' .
                                    labels('to', 'to') . ' ' .
                                    $manifestTo . '.';

                                if ($migrationError !== null) {
                                    $successMsg .= ' ' . labels('migration_failed_warning', 'However, some database migrations failed to run. Please run them manually from the updater page.');
                                }

                                $response['message'] = $successMsg;
                            } else {
                                $response['error'] = true;
                                $response['message'] = labels('invalid_update_file_it_seems_like_you_are_trying_to_update_the_system_using_wrong_file', 'Invalid update file!. It seems like you are trying to update the system using wrong file') . '.';
                                // Delete extracted files (including hidden dotfiles like .DS_Store)
                                delete_files(FCPATH . UPDATE_PATH, true, false, true);
                            }
                        } else {
                            $response['error'] = true;
                            $response['message'] = labels('extraction_failed', 'Extraction failed');
                        }
                    } else {
                        $response['error'] = true;
                        // This error message may be variable. Update the translation later on when you have enough info
                        $response['message'] = $uploadData->getErrorString();
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = labels('you_did_not_select_a_file_to_upload', 'You did not select a file to upload');
                }
                $response['csrfName'] = $security->getTokenName();
                $response['csrfHash'] = $security->getHash();
                return $this->response->setJSON($response);
            } else {
                $response['error'] = true;
                $response['message'] = labels('purchase_code_is_invalid', 'Purchase Code is Invalid');
                // Delete extracted files (including hidden dotfiles like .DS_Store)
                delete_files(FCPATH . UPDATE_PATH, true, false, true);
                return $this->response->setJSON($response);
            }
        } else {
            return redirect()->to('admin/login');
        }
    }

    /**
     * POST endpoint: seeds demo data.
     * Expects: mode = 'additive' | 'clean'
     */
    public function seed_demo_data()
    {
        $security = \Config\Services::security();

        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON([
                'error' => true,
                'message' => 'Unauthorized',
                'csrfName' => $security->getTokenName(),
                'csrfHash' => $security->getHash(),
            ]);
        }

        // Permission check: superadmin always allowed, others need system_update permission
        $permissions = get_permission($this->userId);
        $isSuperAdmin = (strtolower(trim($this->session->get('email') ?? '')) === 'superadmin@gmail.com');

        if (!$isSuperAdmin && empty($permissions['update']['system_update'])) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('no_permission', 'You do not have permission to perform this action.'),
                'csrfName' => $security->getTokenName(),
                'csrfHash' => $security->getHash(),
            ]);
        }

        $mode = $this->request->getPost('mode');
        if (!in_array($mode, ['additive', 'clean'], true)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('invalid_seed_mode', 'Invalid seed mode.'),
                'csrfName' => $security->getTokenName(),
                'csrfHash' => $security->getHash(),
            ]);
        }

        try {
            $seeder = new \App\Database\Seeds\DemoDataSeeder(new \Config\Database());
            $seeder->setMode($mode);
            $seeder->run();

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('demo_data_seeded_successfully', 'Demo data seeded successfully.'),
                'csrfName' => $security->getTokenName(),
                'csrfHash' => $security->getHash(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[DemoSeeder] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->response->setJSON([
                'error' => true,
                'message' => labels('SOMETHING_WENT_WRONG', 'Something went wrong.'),
                'csrfName' => $security->getTokenName(),
                'csrfHash' => $security->getHash(),
            ]);
        }
    }
}
