<?php

namespace App\Controllers\Admin;

use App\Models\Language_model;
use App\Services\LanguageFileService;
use App\Services\utility\FileService;

class Languages extends Admin
{

    protected Language_model $langauge;
    protected LanguageFileService $languageFileService;
    private FileService $fileService;
    protected $superadmin;

    public function __construct()
    {
        parent::__construct();
        $this->langauge = new Language_model();
        $this->languageFileService = new LanguageFileService();
        $this->fileService = new FileService();
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
        helper('language');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Check for legacy files and run migration if needed
        $this->checkAndMigrateLegacyFiles();
        $session = session();
        $lang = $session->get('lang');

        if (empty($lang)) {
            $lang = 'en';
        }
        $this->data['code'] = $lang;
        setPageInfo($this->data, labels('language', 'Language') . ' | ' . labels('admin_panel', 'Admin Panel'), 'languages');
        $this->data['languages'] = fetch_details('languages', [], [], null, '0', 'id', 'ASC');
        return view('backend/admin/template', $this->data);
    }
    public function change($lang)
    {

        try {
            $session = session();
            $session->remove('lang');
            $session->set('lang', $lang);
            return redirect()->to("admin/languages/");
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - change()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function insert()
    {
        try {
            $demoCheck = $this->checkDemoMode();
            if ($demoCheck !== true) return $demoCheck;

            $db   = \Config\Database::connect();
            $name = trim($_POST['language_name']);
            // Ensure language code is a string and convert to lowercase
            // Convert to string first, then trim and convert to lowercase
            $code = strtolower(trim((string)$_POST['language_code']));

            $requestCheck = $this->validateRequest($name, $code);
            if ($requestCheck !== true) return $requestCheck;

            $duplicateCheck = $this->checkDuplicateLanguageCode($db, $code);
            if ($duplicateCheck !== true) return $duplicateCheck;

            $imageResult = $this->handleImageUpload($code);
            if ($imageResult !== true && is_array($imageResult)) {
                $imagePath = $imageResult['path'];
            } elseif ($imageResult !== true) {
                return $imageResult; // error response
            } else {
                $imagePath = null;
            }

            $categories = $this->languageFileService->getSupportedCategories();
            $uploadedFiles = $this->validateAndCollectLanguageFiles($categories);
            if (!is_array($uploadedFiles)) return $uploadedFiles;

            $uploadResult = $this->languageFileService->uploadLanguageFiles($uploadedFiles, $code);
            if (!$uploadResult['success']) {
                return $this->buildErrorResponse(
                    "File upload failed: " . implode(', ', $uploadResult['errors'])
                );
            }

            $languageData = [
                'language' => $name,
                'code'     => $code,
                'is_rtl'   => isset($_POST['is_rtl']) ? '1' : '0',
                'image'    => $imagePath,
            ];

            if ($this->insertLanguageRecord($db, $languageData)) {
                if (isset($uploadResult['uploaded_files']['panel'])) {
                    $this->generatePhpLanguageFile($uploadResult['uploaded_files']['panel'], $code, 'panel');
                }
                return $this->buildSuccessResponse(labels(DATA_SAVED_SUCCESSFULLY, 'Data saved successfully'));
            }

            // Rollback image if DB insert failed
            if ($imagePath) {
                $this->fileService->delete('language_image', $imagePath);
            }

            return $this->buildErrorResponse(labels(ERROR_OCCURED, 'An error occurred'));
        } catch (\Throwable $th) {
            log_the_responce(
                $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - insert()'
            );
            return $this->buildErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }
    public function remove()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $demoCheck = $this->checkDemoMode();
            if ($demoCheck !== true) return $demoCheck;

            $db = \Config\Database::connect();
            $id = $this->request->getVar('id');
            $builder = $db->table('languages');
            $builder->where('id', $id);
            $data = fetch_details("languages", ['id' => $id]);
            if (empty($data)) {
                return redirect('admin/login');
            }
            $code = $data[0]['code'];
            $imagePath = $data[0]['image']; // Get image path for deletion

            // Prevent deletion of whichever language is marked as default.
            // This keeps the active default safe even if the default language changed from English.
            if (!empty($data[0]['is_default']) && (int)$data[0]['is_default'] === 1) {
                return ErrorResponse(labels(DEFAULT_LANGUAGE_CANNOT_BE_REMOVED, "Default language cannot be removed"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if ($builder->delete()) {
                // Delete language files using service
                $deleteResult = $this->languageFileService->deleteLanguageFiles($code);

                // Delete PHP language directory
                $langDir = APPPATH . 'Language/' . $code . '/';
                if (is_dir($langDir)) {
                    delete_directory($langDir);
                }

                // Delete language image if exists
                if ($imagePath) {
                    $this->fileService->delete('language_image', $imagePath);
                }

                // Log any file deletion errors
                if (!$deleteResult['success']) {
                    log_message('warning', 'Some language files could not be deleted: ' . implode(', ', $deleteResult['errors']));
                }

                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - remove()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function language_sample()
    {
        try {
            // Get the category from URL parameter
            $uri = service('uri');
            $segments = $uri->getSegments();
            $category = end($segments); // Get the last segment as category

            // Define valid sample file categories (these match the actual file names)
            $validSampleCategories = ['panel', 'web', 'customer', 'provider'];

            // Validate category
            if (!in_array($category, $validSampleCategories)) {
                $_SESSION['toastMessage'] = labels(INVALID_CATEGORY, 'Invalid category');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/languages')->withCookies();
            }

            // Get the appropriate sample file path for the category
            $sampleFilePath = $this->getSampleFilePath($category);

            if (file_exists($sampleFilePath)) {
                // Set appropriate headers for JSON download
                $headers = [
                    'Content-Type: application/json',
                    'Content-Disposition: attachment; filename="sample_' . $category . '.json"'
                ];

                // Return the file for download
                return $this->response->download($sampleFilePath, null)
                    ->setFileName('sample_' . $category . '.json');
            } else {
                $_SESSION['toastMessage'] = labels(SAMPLE_FILE_NOT_FOUND, 'Sample file not found');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/languages')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - language_sample()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get the sample file path for a specific category
     * 
     * @param string $category The category (panel, web, customer_app, provider_app)
     * @return string The file path for the sample
     */
    private function getSampleFilePath(string $category): string
    {
        $samplesDir = FCPATH . '/public/sample_language_files/';

        // Create samples directory if it doesn't exist
        if (!is_dir($samplesDir)) {
            mkdir($samplesDir, 0755, true);
        }

        return $samplesDir . 'sample_' . $category . '.json';
    }
    public function language_old()
    {
        try {
            $uri = service('uri');
            $segments = $uri->getSegments();

            if (count($segments) < 3) {
                return ErrorResponse(labels(INVALID_DOWNLOAD_REQUEST, "Invalid download request"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $code = $segments[1];
            $category = $segments[2] ?? 'panel'; // Default to panel if no category specified

            // Validate category
            if (!in_array($category, $this->languageFileService->getSupportedCategories())) {
                return ErrorResponse(labels(INVALID_CATEGORY, "Invalid category"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $filePath = $this->languageFileService->getLanguageFilePath($code, $category);

            if ($filePath && file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($code . "_{$category}.json");
            } else {
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/languages')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - language_old()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = [];
            $from_app = false;
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $from_app = true;
            }
            $data = $this->langauge->list($from_app, $search, $limit, $offset, $sort, $order, $where);
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                if (!empty($data['data'])) {
                    return successResponse(labels(DATA_FETCHED_SUCCESSFULLY, "Data fetched successfully"), false, $data['data'], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, $data['data'], [], 200, csrf_token(), csrf_hash());
                }
            }
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $demoCheck = $this->checkDemoMode();
            if ($demoCheck !== true) return $demoCheck;

            helper('files');
            helper('filesystem');

            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $builder = $db->table('languages');
            $builder->select('*')->where('id', $id);
            $language_record = $builder->get()->getRow();
            $old_code = $language_record->code;
            $old_image = $language_record->image; // Store old image path

            // Ensure language code is a string and convert to lowercase
            // Convert to string first, then trim and convert to lowercase
            $code = strtolower(trim((string)$this->request->getPost('edit_code')));
            $name = $this->request->getPost('edit_name');

            // Validate required fields
            if (empty($name) || empty($code)) {
                return ErrorResponse(labels(LANGUAGE_NAME_CODE_REQUIRED, "Language name and code are required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Strict validation: language code must not contain any numbers
            // Check if the code contains any numeric characters
            if (preg_match('/[0-9]/', $code)) {
                return ErrorResponse(
                    labels('language_code_cannot_contain_numbers', 'Language code cannot contain any numbers. Only letters are allowed.'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }

            // Check if language code already exists (excluding current record)
            $existingLanguage = $db->table('languages')->where('code', $code)->where('id !=', $id)->get()->getRow();
            if ($existingLanguage) {
                $message = labels('language_code', 'Language code') . "'" . $code . "' " . labels('already_exists', 'already exists');
                return ErrorResponse($message, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Handle image upload
            $imagePath = $old_image; // Keep existing image by default
            $imageFile = $this->request->getFile('update_language_image');

            if ($imageFile && $imageFile->isValid()) {
                // Validate image file
                $validationResult = $this->validateImageFile($imageFile);
                if (!$validationResult['valid']) {
                    return ErrorResponse($validationResult['message'], true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Replace image file using FileService
                $result = $this->fileService->replace($old_image, $imageFile, 'language_image');
                if ($result['error']) {
                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                }

                $imagePath = $result['path'];
            }

            // Collect uploaded files for different categories (same logic as insert)
            $uploadedFiles = [];
            $categories = $this->languageFileService->getSupportedCategories();
            $panelJsonPath = null; // Track the freshest panel JSON path so Text.php can mirror it immediately

            foreach ($categories as $category) {
                $fileKey = "update_language_json_{$category}";
                $file = $this->request->getFile($fileKey);
                if ($file && $file->isValid()) {
                    $uploadedFiles[$category] = $file;
                }
            }

            // If no files uploaded, check for legacy single file upload
            if (empty($uploadedFiles)) {
                $legacyFile = $this->request->getFile('update_language_json');
                if ($legacyFile && $legacyFile->isValid()) {
                    // Handle legacy upload - save to panel category
                    $uploadedFiles['panel'] = $legacyFile;
                }
            }

            // Process uploaded files (same logic as insert)
            if (!empty($uploadedFiles)) {
                // Delete old files for the categories being updated
                foreach (array_keys($uploadedFiles) as $category) {
                    $oldFilePath = $this->languageFileService->getLanguageFilePath($old_code, $category);
                    // Guard the unlink call because some languages never had files for every category.
                    if ($oldFilePath && file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                        $this->deleteDirectoryIfEmpty(dirname($oldFilePath));
                    }
                }

                // Upload new files using the same service as insert
                $uploadResult = $this->languageFileService->uploadLanguageFiles($uploadedFiles, $code);

                if (!$uploadResult['success']) {
                    $message = labels('file_upload_failed', 'File upload failed') . ": " . implode(', ', $uploadResult['errors']);
                    return ErrorResponse($message, true, [], [], 200, csrf_token(), csrf_hash());
                }

                if (isset($uploadResult['uploaded_files']['panel'])) {
                    // Use the new panel JSON to regenerate Text.php right away so nothing lags behind.
                    $panelJsonPath = $uploadResult['uploaded_files']['panel'];
                    $this->generatePhpLanguageFile($panelJsonPath, $code, 'panel');
                }
            }

            // After handling uploads, rename any leftover files that still reference the old language code.
            if ($old_code !== $code) {
                $skipCategories = array_keys($uploadedFiles);
                $renamedPanelPath = $this->renameLanguageFilesForCodeChange($old_code, $code, $categories, $skipCategories);

                if (!$panelJsonPath && $renamedPanelPath) {
                    $panelJsonPath = $renamedPanelPath;
                }

                $this->renamePanelPhpDirectory($old_code, $code);
            }

            // After uploads/renames, regenerate PHP file from the latest panel JSON (if it exists)
            if (!$panelJsonPath) {
                // When there was no fresh panel upload, rely on the current disk copy.
                $panelJsonPath = $this->languageFileService->getLanguageFilePath($code, 'panel');
            }
            if ($panelJsonPath && file_exists($panelJsonPath)) {
                $this->generatePhpLanguageFile($panelJsonPath, $code, 'panel');
            }

            // Update database record
            $languageData = [
                'language' => $name,
                'code' => $code,
                'is_rtl' => isset($_POST['is_rtl']) ? '1' : '0',
                'image' => $imagePath, // Update image path in database
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $check = $db->table('languages')->where('id', $id)->update($languageData);

            if ($check) {
                $response = [
                    'error' => false,
                    'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Language updated successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ];
                return $this->response->setJSON($response);
            } else {
                // If database update fails and we uploaded a new image, delete it
                if ($imagePath !== $old_image && $imagePath) {
                    $this->fileService->delete('language_image', $imagePath);
                }

                $response = [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED, 'An error occurred'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }


    public function store_default_language()
    {
        try {
            $demoCheck = $this->checkDemoMode();
            if ($demoCheck !== true) return $demoCheck;
            // Validate input
            $languageId = $this->request->getPost('id');
            if (empty($languageId)) {
                return ErrorResponse(labels(LANGUAGE_ID_REQUIRED, "Language ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if the language exists
            $languageExists = fetch_details('languages', ['id' => $languageId]);
            if (empty($languageExists)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get current default language
            $currentDefault = fetch_details('languages', ['is_default' => '1']);

            // Initialize language model
            $languageModel = new Language_model();

            // Start database transaction
            $db = \Config\Database::connect();
            $db->transStart();

            try {
                // Remove default from current default language (if exists)
                if (!empty($currentDefault)) {
                    $updateResult = $languageModel->update($currentDefault[0]['id'], ['is_default' => 0]);
                    if (!$updateResult) {
                        throw new \Exception(labels(COULD_NOT_UPDATE_DEFAULT, "Could not update default"));
                    }
                }

                // Set new default language
                $updateResult = $languageModel->update($languageId, ['is_default' => 1]);
                if (!$updateResult) {
                    throw new \Exception(labels(COULD_NOT_UPDATE_DEFAULT, "Could not update default"));
                }

                // Commit transaction
                $db->transCommit();

                $response = [
                    'error' => false,
                    'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully') . '.',
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];

                return $this->response->setJSON($response);
            } catch (\Exception $e) {
                // Rollback transaction on error
                $db->transRollback();
                throw $e;
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - store_default_language()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Generate PHP language file from JSON
     * 
     * This function securely validates and reads JSON language files to prevent path traversal attacks.
     * Only files from allowed safe directories can be processed.
     * 
     * @param string $jsonFilePath Path to JSON file
     * @param string $languageCode Language code
     * @param string $category File category
     * @return bool Success status
     */
    private function generatePhpLanguageFile(string $jsonFilePath, string $languageCode, string $category = 'panel'): bool
    {
        try {
            // Security: Validate and sanitize the file path to prevent path traversal attacks
            $validatedPath = $this->validateAndSanitizeJsonFilePath($jsonFilePath);
            if ($validatedPath === false) {
                log_message('error', 'Path traversal attempt detected or invalid file path: ' . $jsonFilePath);
                return false;
            }

            // Read JSON content from the validated path
            $jsonContent = file_get_contents($validatedPath);
            if ($jsonContent === false) {
                log_message('error', 'Failed to read JSON file: ' . $validatedPath);
                return false;
            }

            // Decode JSON content
            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Invalid JSON in file: ' . $validatedPath . ' - ' . json_last_error_msg());
                return false;
            }

            // Generate PHP language file content
            $phpContent = "<?php\n";

            foreach ($data as $key => $val) {
                // Escape quotes in values to prevent injection
                $escapedVal = str_replace('"', '\\"', $val);
                $phpContent .= "\$lang['{$key}'] = \"{$escapedVal}\";\n";
            }

            $phpContent .= "return \$lang;";

            // Create language directory if it doesn't exist
            $langDir = APPPATH . 'Language/' . $languageCode . '/';
            if (!is_dir($langDir)) {
                mkdir($langDir, 0777, true);
            }

            // Write PHP file
            $phpFilePath = $langDir . 'Text.php';
            return write_file($phpFilePath, $phpContent) !== false;
        } catch (\Exception $e) {
            log_message('error', 'Failed to generate PHP language file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate and sanitize JSON file path to prevent path traversal attacks
     * 
     * This method uses CodeIgniter 4's security helpers along with directory whitelist
     * validation to ensure only safe files can be accessed.
     * 
     * @param string $jsonFilePath The file path to validate
     * @return string|false Returns the validated absolute path on success, false on failure
     */
    private function validateAndSanitizeJsonFilePath(string $jsonFilePath): string|false
    {
        // Load CI4's Security helper for filename sanitization
        helper('security');

        // Define allowed base directory where JSON files can be loaded from
        $allowedBaseDir = FCPATH . 'public/uploads/languages/';

        // Remove null bytes from input path (security measure)
        $jsonFilePath = str_replace("\0", '', $jsonFilePath);

        // Validate that the path is not empty
        if (empty(trim($jsonFilePath))) {
            return false;
        }

        // Use realpath() to resolve symlinks, relative paths, and directory traversal attempts
        // realpath() returns false if the path doesn't exist or contains invalid components
        $resolvedPath = realpath($jsonFilePath);
        if ($resolvedPath === false) {
            return false;
        }

        // Validate file extension is .json
        if (strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION)) !== 'json') {
            return false;
        }

        // Ensure it's a file, not a directory
        if (!is_file($resolvedPath)) {
            return false;
        }

        // Security: Ensure the resolved path is within the allowed directory
        // This is the critical check that prevents path traversal attacks
        $normalizedAllowedDir = realpath($allowedBaseDir);
        if ($normalizedAllowedDir === false) {
            return false; // Allowed directory doesn't exist
        }

        $normalizedAllowedDir = rtrim($normalizedAllowedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Check if resolved path is within allowed directory
        if (strpos($resolvedPath, $normalizedAllowedDir) !== 0) {
            return false; // Path traversal attempt detected
        }

        // Use CI4's sanitize_filename() to sanitize the filename part
        // This adds an extra layer of protection against malicious filenames
        $filename = basename($resolvedPath);
        $sanitizedFilename = sanitize_filename($filename);

        // If sanitization changed the filename, it contained dangerous characters
        if ($filename !== $sanitizedFilename) {
            return false;
        }

        // Return the validated and resolved absolute path
        return $resolvedPath;
    }

    /**
     * Rename existing language files when only the language code changes.
     * This keeps every category (panel/web/customer_app/provider_app) aligned with the new code.
     * We skip categories that already received fresh uploads in the current request.
     *
     * @param string $oldCode        Previous language code (directory / filename)
     * @param string $newCode        New language code selected by admin
     * @param array  $categories     Supported categories we need to sweep
     * @param array  $skipCategories Categories that already have newly uploaded files
     *
     * @return string|null Absolute path of the renamed panel file so PHP translations can refresh
     */
    private function renameLanguageFilesForCodeChange(string $oldCode, string $newCode, array $categories, array $skipCategories = []): ?string
    {
        $renamedPanelPath = null;

        foreach ($categories as $category) {
            if (in_array($category, $skipCategories, true)) {
                continue; // Fresh uploads already cover this category.
            }

            $oldDir = $this->languageFileService->buildLanguageDirectoryPath($oldCode, $category);
            if (!is_dir($oldDir)) {
                continue;
            }

            $newDir = $this->languageFileService->buildLanguageDirectoryPath($newCode, $category);
            $oldFilePath = $oldDir . '/' . strtolower($oldCode) . '.json';
            $newFilePath = $newDir . '/' . strtolower($newCode) . '.json';

            if (!is_dir($newDir)) {
                if (@rename($oldDir, $newDir)) {
                    $movedOldFilePath = $newDir . '/' . strtolower($oldCode) . '.json';
                    if (file_exists($movedOldFilePath) && $movedOldFilePath !== $newFilePath) {
                        @rename($movedOldFilePath, $newFilePath);
                    }
                    if ($category === 'panel' && file_exists($newFilePath)) {
                        $renamedPanelPath = $newFilePath;
                    }
                    continue;
                } else {
                    log_message('error', "Failed to rename directory {$oldDir} to {$newDir} during language code update.");
                }
            }

            if (!is_dir($newDir) && !mkdir($newDir, 0755, true) && !is_dir($newDir)) {
                log_message('error', "Failed to create language directory {$newDir} for language code rename.");
                continue;
            }

            if (!file_exists($oldFilePath)) {
                $this->deleteDirectoryIfEmpty($oldDir);
                continue;
            }

            if (!@rename($oldFilePath, $newFilePath)) {
                log_message('error', "Failed to rename language file from {$oldFilePath} to {$newFilePath}");
                continue;
            }

            if ($category === 'panel') {
                $renamedPanelPath = $newFilePath;
            }

            $this->deleteDirectoryIfEmpty($oldDir);
        }

        return $renamedPanelPath;
    }

    /**
     * Rename the PHP translation directory used by the admin panel when the language code changes.
     * This keeps Text.php aligned with the new code so dropdowns keep detecting the language.
     */
    private function renamePanelPhpDirectory(string $oldCode, string $newCode): void
    {
        if ($oldCode === $newCode) {
            return;
        }

        $oldDir = APPPATH . 'Language/' . $oldCode . '/';
        $newDir = APPPATH . 'Language/' . $newCode . '/';

        if (!is_dir($oldDir)) {
            return; // Nothing to rename.
        }

        // If the new directory already exists we only need to move Text.php when it is missing there.
        if (is_dir($newDir)) {
            $oldText = $oldDir . 'Text.php';
            $newText = $newDir . 'Text.php';

            if (!file_exists($newText) && file_exists($oldText)) {
                if (!@rename($oldText, $newText)) {
                    log_message('error', "Failed to move panel Text.php from {$oldText} to {$newText}");
                }
            }

            $this->deleteDirectoryIfEmpty($oldDir);
            return;
        }

        if (!@rename($oldDir, $newDir)) {
            log_message('error', "Failed to rename panel language directory from {$oldDir} to {$newDir}");
        }
    }

    /**
     * Remove a directory when it no longer contains any files.
     */
    private function deleteDirectoryIfEmpty(?string $dirPath): void
    {
        if (!$dirPath || !is_dir($dirPath)) {
            return;
        }

        $items = @scandir($dirPath);
        if ($items === false) {
            return;
        }

        $visibleItems = array_diff($items, ['.', '..']);
        if (empty($visibleItems)) {
            @rmdir($dirPath);
        }
    }

    /**
     * Check for legacy files and automatically migrate if found
     * This method runs once when the languages page is opened
     */
    private function checkAndMigrateLegacyFiles()
    {
        try {
            // Load the language helper
            helper('language');

            // Check if migration has already been run (using session flag)
            $session = session();
            $migrationRun = $session->get('legacy_migration_completed');

            if ($migrationRun) {
                return; // Migration already completed, skip
            }

            // Check for legacy files
            $legacyFiles = get_legacy_language_files();

            if (!empty($legacyFiles)) {
                // Legacy files found, run migration
                $migrationResult = migrate_all_legacy_language_files();

                if ($migrationResult['success']) {
                    // Set session flag to prevent future migrations
                    $session->set('legacy_migration_completed', true);

                    // Show success message
                    $_SESSION['toastMessage'] = labels(LEGACY_MIGRATION_COMPLETED, "Legacy migration completed");
                    $_SESSION['toastMessageType'] = 'success';
                } else {
                    // Show warning message
                    $_SESSION['toastMessage'] = labels(ERROR_OCCURED, "An error occurred");
                    $_SESSION['toastMessageType'] = 'warning';
                }

                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
            } else {
                // No legacy files found, mark migration as completed
                $session->set('legacy_migration_completed', true);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - checkAndMigrateLegacyFiles()');
            // Don't show error to user, just log it
        }
    }

    /**
     * Get current language list as JSON for dropdown refresh
     */
    public function get_languages_for_dropdown()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse("Unauthorized access", true, [], [], 200, csrf_token(), csrf_hash());
            }

            $db = \Config\Database::connect();
            $languages = $db->table('languages')
                ->select('id, language, code, is_rtl')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            // Filter languages to only include those with panel files
            $available_languages = [];
            $languageModel = new \App\Models\Language_model();

            foreach ($languages as $language) {
                if ($languageModel->hasPanelLanguageFiles($language['code'])) {
                    $available_languages[] = $language;
                }
            }

            // Get default language
            $default_language = $db->table('languages')
                ->select('id, code')
                ->where('is_default', '1')
                ->get()
                ->getRow();

            $response = [
                'error' => false,
                'languages' => $available_languages,
                'default_language' => $default_language,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ];

            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Languages.php - get_languages_for_dropdown()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Validate uploaded image file
     * 
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file The uploaded file
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    private function validateImageFile($file): array
    {
        // Check file size
        if ($file->getSize() > IMAGE_MAX_SIZE) {
            return [
                'valid' => false,
                'message' => labels(FILE_SIZE_TOO_LARGE, 'File size is too large. Maximum allowed size is 2MB.')
            ];
        }

        // Check file extension
        $extension = strtolower($file->getExtension());
        if (!in_array($extension, LANGUAGE_IMAGE_ALLOWED_EXTENSIONS)) {
            return [
                'valid' => false,
                'message' => labels('invalid_file_type', 'Invalid file type. Allowed types: ') . implode(', ', LANGUAGE_IMAGE_ALLOWED_EXTENSIONS)
            ];
        }

        // Validate image using getimagesize
        $imageInfo = getimagesize($file->getTempName());
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'message' => labels(INVALID_IMAGE_FILE, 'Invalid image file.')
            ];
        }

        return [
            'valid' => true,
            'message' => ''
        ];
    }

    private function checkDemoMode()
    {
        // If superadmin, bypass demo mode restrictions
        if ($this->superadmin === "superadmin@gmail.com") {
            // Force modifications allowed for superadmin
            defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            return true;
        }

        // Check if modifications are restricted in demo mode
        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
            $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, "Modification in demo version is not allowed");
            $_SESSION['toastMessageType'] = 'error';

            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');

            return redirect()
                ->to('admin/settings/general-settings')
                ->withCookies();
        }

        return true;
    }


    private function validateRequest($name, $code)
    {
        // Validate that name and code are not empty
        if (empty($name) || empty($code)) {
            return $this->buildErrorResponse(labels(LANGUAGE_NAME_CODE_REQUIRED, "Language name and code are required"));
        }

        // Strict validation: language code must not contain any numbers
        // Check if the code contains any numeric characters
        if (preg_match('/[0-9]/', $code)) {
            return $this->buildErrorResponse(
                labels('language_code_cannot_contain_numbers', 'Language code cannot contain any numbers. Only letters are allowed.')
            );
        }

        return true;
    }

    private function checkDuplicateLanguageCode($db, $code)
    {
        $existingLanguage = $db->table('languages')->where('code', $code)->get()->getRow();
        if ($existingLanguage) {
            return $this->buildErrorResponse(
                labels('language_code', 'Language code') . " '" . $code . "' " . labels('already_exists', 'already exists')
            );
        }
        return true;
    }

    private function handleImageUpload($code)
    {
        $imageFile = $this->request->getFile('language_image');
        if ($imageFile && $imageFile->isValid()) {
            $validationResult = $this->validateImageFile($imageFile);
            if (!$validationResult['valid']) {
                return $this->buildErrorResponse($validationResult['message']);
            }

            $result = $this->fileService->upload($imageFile, 'language_image');
            if ($result['error']) {
                return $this->buildErrorResponse($result['message']);
            }

            return ['success' => true, 'path' => $result['path']];
        }
        return true; // no file uploaded = optional
    }

    private function validateAndCollectLanguageFiles($categories)
    {
        $uploadedFiles     = [];
        $missingCategories = [];

        foreach ($categories as $category) {
            $fileKey = "language_json_{$category}";
            $file    = $this->request->getFile($fileKey);

            if ($file && $file->isValid()) {
                if ($file->getClientExtension() !== 'json') {
                    $message = labels('invalid_file_type', 'Invalid file type') . " " . labels('for') . " " . str_replace('_', ' ', labels($category, $category)) . " " . labels('only_json_files_allowed', 'Only JSON files are allowed.');
                    return $this->buildErrorResponse($message);
                }

                $content = file_get_contents($file->getTempName());
                if (empty(trim($content))) {
                    $message = labels('file_cannot_be_empty', 'File ') . " " . str_replace('_', ' ', labels($category, $category)) . " " . labels('cannot_be_empty', 'cannot be empty.');
                    return $this->buildErrorResponse($message);
                }

                $json = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                    $message = labels('file_contains_invalid_json', 'File ') . " " . str_replace('_', ' ', labels($category, $category)) . " " . labels('contains_invalid_json', 'contains invalid JSON.');
                    return $this->buildErrorResponse($message);
                }

                $uploadedFiles[$category] = $file;
            } else {
                $missingCategories[] = $category;
            }
        }

        if (!empty($missingCategories)) {
            $message = labels('all_language_files_must_be_uploaded', 'All language files must be uploaded. Missing: ') . implode(', ', str_replace('_', ' ', labels($missingCategories)));
            return $this->buildErrorResponse($message);
        }

        return $uploadedFiles;
    }

    private function insertLanguageRecord($db, $data)
    {
        return $db->table('languages')->insert($data);
    }

    private function buildSuccessResponse($message)
    {
        return $this->response->setJSON([
            'error'    => false,
            'message'  => $message,
            'csrfName' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    private function buildErrorResponse($message)
    {
        return $this->response->setJSON([
            'error'    => true,
            'message'  => $message,
            'csrfName' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }
}
