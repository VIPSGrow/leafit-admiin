<?php

namespace App\Controllers\Partner;

use App\Models\Partners_model;
use App\Services\utility\FileService;
use Aws\S3\S3Client;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Gallery extends Partner
{
    public  $validations, $db;
    protected Partners_model $partner;

    public function __construct()
    {
        parent::__construct();
        $this->db      = \Config\Database::connect();
    }

    public function index()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        $user_id = $this->ionAuth->user()->row()->id;
        setPageInfo($this->data, labels('gallery', 'Gallery') . ' | ' . labels('provider_panel', 'Provider Panel'), 'gallery');

        $settings = get_settings('general_settings', true);
        $file_manager = $settings['file_manager'];

        $not_allowed_folders = [
            'mpdf',
            'tools',
            'css',
            'fonts',
            'js',
            'categories',
            'chat_attachement',
            'languages',
            'ratings',
            'media',
            'notification',
            'offers',
            'provider_bulk_upload',
            'site',
            'sliders',
            'users',
            'img',
            'images'
        ];

        $partnerFiles = [];
        $foldersToCheck = [];
        $folderData = [];

        // Fetch files from database
        $partner = [[]];
        $services = fetch_details('services', ['user_id' => $user_id], ['image', 'other_images', 'files']);
        $orders = fetch_details('orders', ['partner_id' => $user_id], ['work_started_proof', 'work_completed_proof']);

        $fieldRows = $this->db->table('custom_fields')
            ->select(['id'])
            ->where('field_group', 'documents')
            ->where('field_type', 'file')
            ->where('visible', 1)
            ->get()
            ->getResultArray();

        $customFieldIds = array_column($fieldRows, 'id');

        if (!empty($customFieldIds)) {
            $valueRows = $this->db->table('partner_custom_fields')
                ->select(['custom_field_id', 'value'])
                ->where('partner_id', (int) $user_id)
                ->whereIn('custom_field_id', $customFieldIds)
                ->get()
                ->getResultArray();

            foreach ($valueRows as $row) {
                $val = $row['value'] ?? '';
                if ($val !== '') {
                    $partner[0]['cf_' . $row['custom_field_id']] = $val;
                }
            }
        }

        // Helper function to decode or split files
        $parseFiles = static function ($data) {
            $files = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $files = explode(',', $data);
            }
            return is_array($files) ? array_filter($files) : [];
        };

        // Normalize every stored reference so we always end up with "folder/filename.ext"
        $normalizeReference = static function (string $field, $file): ?string {
            if (!is_string($file) || trim($file) === '') {
                return null;
            }

            $reference = ltrim(str_replace('\\', '/', trim($file)), '/');

            // Strip absolute-ish prefixes saved in DB (e.g. "public/uploads/banner/...")
            $reference = preg_replace('#^(public/uploads/|public/backend/assets/)#', '', $reference);

            // If nothing else remains, bail out early
            if ($reference === '' || $reference === false) {
                return null;
            }

            // Some rows already include their folder (e.g. "banner/file.jpg"). Others are just a filename.
            if (strpos($reference, '/') === false) {
                $reference = $field . '/' . $reference;
            }

            return ltrim($reference, '/');
        };

        foreach ($customFieldIds as $cfId) {
            $cfKey = 'cf_' . $cfId;
            if (!empty($partner[0][$cfKey])) {
                $files = $parseFiles($partner[0][$cfKey]);
                foreach ($files as $file) {
                    $normalized = $normalizeReference('custom_fields', $file);
                    if ($normalized) {
                        $partnerFiles[] = $normalized;
                    }
                }
            }
        }

        // Collect service files
        foreach ($services as $service) {
            foreach (['image', 'other_images', 'files'] as $field) {
                if (!empty($service[$field])) {
                    $files = $parseFiles($service[$field]);
                    foreach ($files as $file) {
                        $normalized = $normalizeReference($field, $file);
                        if ($normalized) {
                            $partnerFiles[] = $normalized;
                        }
                    }
                }
            }
        }

        // Collect order files
        foreach ($orders as $order) {
            foreach (['work_started_proof', 'work_completed_proof'] as $field) {
                if (!empty($order[$field])) {
                    $files = $parseFiles($order[$field]);
                    foreach ($files as $file) {
                        $normalized = $normalizeReference($field, $file);
                        if ($normalized) {
                            $partnerFiles[] = $normalized;
                        }
                    }
                }
            }
        }

        // Remove duplicates and empty entries
        $partnerFiles = array_unique(array_filter($partnerFiles));

        // Basename set of files that truly belong to this provider. Used so
        // folder counts reflect only the provider's files (and match exactly
        // what GetGallaryFiles() lists when the folder is opened).
        $allowedBasenames = $this->resolveProviderFileBasenames((int) $user_id);

        // Determine file manager (AWS or Local)
        if ($file_manager == "aws_s3") {
            // Fetch details from AWS S3
            foreach ($partnerFiles as $filePath) {
                $folder = explode('/', $filePath)[0];
                if (in_array($folder, $not_allowed_folders)) {
                    continue;
                }

                // Check if folder data already exists to avoid redundant API calls
                if (!in_array($folder, $foldersToCheck)) {
                    $foldersToCheck[] = $folder;
                    $result = get_aws_s3_folder_info($folder, $allowedBasenames);

                    if (isset($result['data']) && !$result['error']) {
                        foreach ($result['data'] as $value) {
                            // Hide folders that hold none of this provider's files.
                            if ((int) $value['fileCount'] === 0) {
                                continue;
                            }
                            $folderData[] = [
                                'name' => $value['name'],
                                'file_count' => $value['fileCount'],
                                'path' => $value['path']
                            ];
                        }
                    }
                }
            }
        } elseif ($file_manager == "local_server") {
            // On local disks we only care about folders that actually contain this partner's files.
            // Instead of scanning every directory and then intersecting paths, we resolve each DB reference
            // to its real folder. This avoids false negatives when the storage layout changes or when the
            // database stores already-prefixed paths (e.g. provider_work_evidence/data_x.png).
            $rootDirectories = [
                'public/uploads' => rtrim(FCPATH, '/') . '/public/uploads',
                'public/backend/assets' => rtrim(FCPATH, '/') . '/public/backend/assets',
            ];

            $folderSummary = [];

            foreach ($partnerFiles as $fileReference) {
                if (strpos($fileReference, '/') === false) {
                    // Malformed entry without folder context.
                    continue;
                }

                [$folderName, $nestedPath] = array_pad(explode('/', $fileReference, 2), 2, '');
                $folderName = trim($folderName);
                $nestedPath = ltrim(str_replace(['..', '\\'], ['', '/'], trim($nestedPath)), '/');

                if ($folderName === '' || $nestedPath === '' || in_array($folderName, $not_allowed_folders, true)) {
                    continue;
                }

                $matched = false;

                foreach ($rootDirectories as $relativeRoot => $absoluteRoot) {
                    $folderAbsolutePath = $absoluteRoot . '/' . $folderName;
                    if (!is_dir($folderAbsolutePath)) {
                        // Skip missing folders for this root.
                        continue;
                    }

                    // Try the nested path as-is and fall back to its basename (covers cases where DB stored subdirectories
                    // but files were flattened during upload).
                    $candidateFiles = [
                        $folderAbsolutePath . '/' . $nestedPath,
                        $folderAbsolutePath . '/' . basename($nestedPath),
                    ];

                    foreach ($candidateFiles as $candidateFile) {
                        if (
                            is_file($candidateFile) &&
                            !preg_match('/\.(txt|html)$/i', $candidateFile) &&
                            isset($allowedBasenames[basename($candidateFile)])
                        ) {
                            $relativeFolderPath = $relativeRoot . '/' . $folderName;

                            if (!isset($folderSummary[$relativeFolderPath])) {
                                $folderSummary[$relativeFolderPath] = [
                                    'name' => $folderName,
                                    'path' => $relativeFolderPath,
                                    // Distinct basenames so the same physical file
                                    // referenced by multiple DB fields counts once.
                                    'files' => [],
                                ];
                            }

                            $folderSummary[$relativeFolderPath]['files'][basename($candidateFile)] = true;
                            $matched = true;
                            break;
                        }
                    }

                    if ($matched) {
                        break;
                    }
                }
            }

            foreach ($folderSummary as $summary) {
                $folderData[] = [
                    'name' => $summary['name'],
                    'path' => $summary['path'],
                    'file_count' => count($summary['files']),
                ];
            }
        }

        // Set view data
        $this->data['folders'] = $folderData;
        $this->data['users'] = fetch_details('users', ['id' => $user_id], ['company']);

        return view('backend/partner/template', $this->data);
    }



    public function GetGallaryFiles()
    {
        // Ensure the user is logged in
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }
        $user_id = $this->ionAuth->user()->row()->id;
        $uri = service('uri');
        $segments = $uri->getSegments();
        $settings = get_settings('general_settings', true);
        $file_manager = $settings['file_manager'];
        $not_allowed_folders = [
            'mpdf',
            'tools',
            'css',
            'fonts',
            'js',
            'categories',
            'chat_attachement',
            'languages',
            'ratings',
            'media',
            'notification',
            'offers',
            'provider_bulk_upload',
            'site',
            'sliders',
            'users',
            'img',
            'images'
        ];
        if ($file_manager == "aws_s3") {
            $files = get_provider_files_from_aws_s3_folder($segments);

            $cfValues = $this->getPartnerCustomFileValues((int) $user_id);
            $details = [$cfValues];

            $services = fetch_details('services', ['user_id' => $user_id], ['image', 'other_images', 'files']);
            $details = array_merge($details, $services);

            $orders = fetch_details('orders', ['partner_id' => $user_id], ['work_started_proof', 'work_completed_proof']);
            $details = array_merge($details, $orders);

            $new_path = implode('/', array_slice($segments, array_search('get-gallery-files', $segments) + 1));

            $cfKeys = array_keys($cfValues);
            $carry = [];
            foreach ($details as $item) {
                foreach (array_merge($cfKeys, ['image', 'other_images', 'files', 'work_started_proof', 'work_completed_proof']) as $field) {
                    if (!empty($item[$field])) {
                        $filesList = json_decode($item[$field], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $filesList = explode(',', $item[$field]);
                        }
                        foreach ($filesList as $file) {
                            $fileName = basename($file);
                            foreach ($files as $awsFile) {

                                if ($fileName === $awsFile['name']) {
                                    $carry[] = $fileName;
                                }
                            }
                        }
                    }
                }
            }

            $allFiles = array_unique(array_filter($carry));

            $filesData = [];
            foreach ($files as $file) {
                if (in_array($file['name'], $allFiles) && $file['type'] != "text/html") {
                    $filesData[] = $file;
                }
            }
        } else if ($file_manager == "local_server") {
            $new_path = implode('/', array_slice($segments, array_search('get-gallery-files', $segments) + 1));
            $folderPath = rtrim(FCPATH, '/') . '/' . $new_path;

            $files = glob($folderPath . '/*');

            $cfValues = $this->getPartnerCustomFileValues((int) $user_id);
            $details = [$cfValues];

            $services = fetch_details('services', ['user_id' => $user_id], ['image', 'other_images', 'files']);
            $details = array_merge($details, $services);

            $orders = fetch_details('orders', ['partner_id' => $user_id], ['work_started_proof', 'work_completed_proof']);
            $details = array_merge($details, $orders);

            $cfKeys = array_keys($cfValues);
            $carry = [];
            foreach ($details as $item) {
                foreach (array_merge($cfKeys, ['image', 'other_images', 'files', 'work_started_proof', 'work_completed_proof']) as $field) {
                    if (!empty($item[$field])) {
                        $filesList = json_decode($item[$field], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $filesList = explode(',', $item[$field]);
                        }
                        foreach ($filesList as $file) {
                            $filePath = rtrim(FCPATH, '/') . '/' . $new_path . '/' . basename($file);
                            if (file_exists($filePath)) {
                                $carry[] = basename($file);
                            }
                        }
                    }
                }
            }

            $allFiles = array_unique(array_filter($carry));

            $filesData = [];
            foreach ($files as $file) {
                if (in_array(basename($file), $allFiles) && mime_content_type($file) != "text/html") {
                    $filesData[] = [
                        'name' => basename($file),
                        'type' => mime_content_type($file),
                        'size' => $this->formatFileSize(filesize($file)),
                        'full_path' => (new FileService())->url($new_path . '/' . basename($file), ''),
                        'path' => $new_path . '/' . basename($file),
                    ];
                }
            }
        }

        $this->data['files'] = array_filter($filesData);
        $this->data['folder_name'] = end($segments);
        $this->data['total_files'] = count($this->data['files']);
        $this->data['path'] = $new_path;
        $this->data['disk'] = $file_manager;

        setPageInfo($this->data, labels('gallery', 'Gallery') . '-' . labels(normalize_folder_name($this->data['folder_name']), $this->data['folder_name']) . ' | ' . labels('provider_application', 'Provider Panel'), 'gallery_files');
        return view('backend/partner/template', $this->data);
    }

    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Resolve the set of file basenames that actually belong to the logged-in
     * provider. Mirrors the DB lookup used by GetGallaryFiles() so that a
     * download can never leak another provider's files that happen to live in
     * the same shared upload folder (e.g. the global "banner" directory).
     *
     * @return array<string,true> map of basename => true for O(1) lookups
     */
    private function resolveProviderFileBasenames(int $user_id): array
    {
        $cfValues = $this->getPartnerCustomFileValues($user_id);
        $details = [$cfValues];

        $services = fetch_details('services', ['user_id' => $user_id], ['image', 'other_images', 'files']);
        $details = array_merge($details, $services);

        $orders = fetch_details('orders', ['partner_id' => $user_id], ['work_started_proof', 'work_completed_proof']);
        $details = array_merge($details, $orders);

        $cfKeys = array_keys($cfValues);
        $basenames = [];
        foreach ($details as $item) {
            foreach (array_merge($cfKeys, ['image', 'other_images', 'files', 'work_started_proof', 'work_completed_proof']) as $field) {
                if (empty($item[$field])) {
                    continue;
                }

                $filesList = json_decode($item[$field], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $filesList = explode(',', $item[$field]);
                }

                foreach ((array) $filesList as $file) {
                    $name = basename(trim((string) $file));
                    if ($name !== '') {
                        $basenames[$name] = true;
                    }
                }
            }
        }

        return $basenames;
    }

    private function getPartnerCustomFileValues(int $user_id): array
    {
        $fieldRows = $this->db->table('custom_fields')
            ->select('id')
            ->where('field_group', 'documents')
            ->where('field_type', 'file')
            ->where('visible', 1)
            ->get()
            ->getResultArray();

        if (empty($fieldRows)) {
            return [];
        }

        $ids = array_column($fieldRows, 'id');

        $valueRows = $this->db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', $user_id)
            ->whereIn('custom_field_id', $ids)
            ->get()
            ->getResultArray();

        $values = [];
        foreach ($valueRows as $row) {
            if (!empty($row['value'])) {
                $values['cf_' . $row['custom_field_id']] = $row['value'];
            }
        }
        return $values;
    }

    /**
     * Recursively delete a directory.
     * This cleans up the temporary folders we create while building ZIP archives.
     * Without this helper, the download routine throws an error when it tries to call the undefined method.
     */
    private function deleteDirectory($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function downloadAll()
    {

        if (!$this->isLoggedIn) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unauthorized']);
        }

        $user_id = $this->ionAuth->user()->row()->id;
        $allowedFiles = $this->resolveProviderFileBasenames((int) $user_id);

        $folder = $this->request->getPost('folder');
        $full_path = $this->request->getPost('full_path');
        $file_manager = $this->request->getPost('disk');

        if ($file_manager == "aws_s3") {
            try {
                $S3_settings = get_settings('general_settings', true);
                $aws_key = $S3_settings['aws_access_key_id'] ?? '';
                $aws_secret = $S3_settings['aws_secret_access_key'] ?? '';
                $region = $S3_settings['aws_region'] ?? 'us-east-1';
                $bucket_name = $S3_settings['aws_bucket'] ?? '';

                if (!$aws_key || !$aws_secret || !$bucket_name || !$region) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'error' => 'AWS configuration missing'
                    ]);
                }

                $s3 = new S3Client([
                    'version' => 'latest',
                    'region'  => $region,
                    'credentials' => [
                        'key'    => $aws_key,
                        'secret' => $aws_secret,
                    ]
                ]);

                // Ensure folder path ends with '/'
                $folder_path = rtrim($full_path, '/') . '/';

                // List all objects in the folder
                $objects = $s3->getPaginator('ListObjectsV2', [
                    'Bucket' => $bucket_name,
                    'Prefix' => $folder_path
                ]);

                // Create temporary directory for zip files
                $temp_dir = FCPATH . 'public/uploads/temp/' . uniqid('s3_');
                if (!is_dir($temp_dir)) {
                    mkdir($temp_dir, 0777, true);
                }

                // Download each file from S3
                foreach ($objects as $result) {
                    foreach ($result['Contents'] as $object) {
                        // Skip if it's the folder itself
                        if ($object['Key'] === $folder_path) {
                            continue;
                        }

                        // Skip files that do not belong to this provider.
                        if (!isset($allowedFiles[basename($object['Key'])])) {
                            continue;
                        }

                        // Create local directory structure
                        $relative_path = substr($object['Key'], strlen($folder_path));
                        $local_path = $temp_dir . '/' . $relative_path;
                        $local_dir = dirname($local_path);

                        if (!is_dir($local_dir)) {
                            mkdir($local_dir, 0777, true);
                        }

                        // Download file from S3
                        $s3->getObject([
                            'Bucket' => $bucket_name,
                            'Key'    => $object['Key'],
                            'SaveAs' => $local_path
                        ]);
                    }
                }

                // Create ZIP file
                $zipName = $folder . '.zip';
                $zipPath = FCPATH . 'public/uploads/' . $zipName;

                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($temp_dir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    foreach ($files as $name => $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($temp_dir) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }

                    $zip->close();

                    // Clean up temporary directory
                    $this->deleteDirectory($temp_dir);

                    // Send the ZIP file
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zipName . '"');
                    header('Content-Length: ' . filesize($zipPath));
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($zipPath);
                    unlink($zipPath);
                    exit;
                } else {
                    $this->deleteDirectory($temp_dir);
                    return $this->response->setStatusCode(500)->setJSON(['error' => 'Could not create zip file']);
                }
            } catch (Exception $e) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error' => 'AWS Error: ' . $e->getMessage()
                ]);
            }
        } else if ($file_manager == 'local_server') {
            $folderPath = FCPATH . $full_path;

            if (!is_dir($folderPath) || strpos(realpath($folderPath), FCPATH) !== 0) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid folder']);
            }

            $zipName = $folder . '.zip';
            $zipPath = FCPATH . 'public/uploads/' . $zipName;
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($folderPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir() && isset($allowedFiles[$file->getFilename()])) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($folderPath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }

                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($zipPath));
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($zipPath);
                unlink($zipPath);
                exit;
            } else {
                return $this->response->setStatusCode(500)->setJSON(['error' => 'Could not create zip file']);
            }
        } else {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Invalid file manager type']);
        }
    }
}
