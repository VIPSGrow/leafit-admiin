<?php

namespace App\Services\utility;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use Throwable;

/**
 * Centralized file service for upload, delete, URL resolution, and
 * existence checks across local_server and aws_s3 disks.
 *
 * Self-contained: uses CI4 native facilities (UploadedFile::move,
 * UploadedFile::getRandomName, base_url) and the AWS SDK directly.
 * No dependence on project helpers in app/Helpers/function_helper.php.
 *
 * DB storage convention (going forward — both disks store relative paths):
 *   - local_server: "public/uploads/profile/abc.jpg"
 *   - aws_s3:       "profile/abc.jpg"
 *
 * Read paths (url/delete/exists) tolerate legacy filename-only values:
 * the folder argument is authoritative and is re-prepended when missing.
 */
class FileService
{
    private const DEFAULT_IMAGE = 'public/backend/assets/default.png';

    /** Fallback re-encode quality when not configured in settings. */
    private const COMPRESS_QUALITY_DEFAULT = 70;

    /** Cache keys + TTL for disk/settings lookups. */
    private const CACHE_KEY_DISK = 'file_service.disk';
    private const CACHE_KEY_SETTINGS = 'file_service.settings';
    private const CACHE_TTL = 300;

    /** Extensions that the CI4 image handler can safely re-encode. */
    private const COMPRESSIBLE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    private array $folderMap = [
        'profile' => 'public/uploads/profile/',
        'profiles' => 'public/uploads/profiles/',
        'banner' => 'public/uploads/banner/',
        'categories' => 'public/uploads/categories/',
        'site' => 'public/uploads/site/',
        'custom_fields' => 'public/uploads/custom_fields/',
        'partner' => 'public/uploads/partner/',
        'sliders' => 'public/uploads/sliders/',
        'services' => 'public/uploads/services/',
        'feature_section' => 'public/uploads/feature_section/',
        'ratings' => 'public/uploads/ratings/',
        'promocodes' => 'public/uploads/promocodes/',
        'become_provider' => 'public/uploads/become_provider/',
        'provider_work_evidence' => 'public/uploads/provider_work_evidence/',
        'seo_settings' => 'public/uploads/seo_settings/general_seo_settings/',
        'service_seo_settings' => 'public/uploads/seo_settings/service_seo_settings/',
        'category_seo_settings' => 'public/uploads/seo_settings/category_seo_settings/',
        'provider_seo_settings' => 'public/uploads/seo_settings/provider_seo_settings/',
        'blog_seo_settings' => 'public/uploads/seo_settings/blog_seo_settings/',
        'custom_page_seo_settings' => 'public/uploads/seo_settings/custom_page_seo_settings/',
        'blogs' => 'public/uploads/blogs/',
        'blogs/images' => 'public/uploads/blogs/images/',
        'country_flags' => 'public/backend/assets/country_flags/',
        'language_image' => 'public/uploads/languages/images/',
        'login_image' => 'public/uploads/site/',
        'login_image_legacy' => 'public/frontend/retro/',
    ];

    private ?string $diskCache = null;
    private ?array $settingsCache = null;
    private ?S3Client $s3Cache = null;

    public function __construct()
    {
        helper('url');
    }

    /**
     * Upload a file to the active disk.
     *
     * @return array{error: bool, path: ?string, disk: string, message: ?string}
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        $disk = $this->disk();

        if (!$file->isValid()) {
            return [
                'error' => true,
                'path' => null,
                'disk' => $disk,
                'message' => $file->getErrorString(),
            ];
        }

        $name = $file->getRandomName();

        return $disk === 'aws_s3'
            ? $this->uploadToS3($file, $folder, $name)
            : $this->uploadToLocal($file, $folder, $name);
    }

    /**
     * Upload a new file and, on success, delete the previously stored
     * file at $oldPath. Order is upload-first so a failed upload does
     * not leave the record with a missing image.
     *
     * Null/empty $oldPath is treated as a plain upload.
     *
     * @return array{error: bool, path: ?string, disk: string, message: ?string}
     */
    public function replace(?string $oldPath, UploadedFile $file, string $folder): array
    {
        $result = $this->upload($file, $folder);

        if (!$result['error'] && $oldPath !== null && trim($oldPath) !== '') {
            $this->delete($folder, $oldPath);
        }

        return $result;
    }

    /**
     * Copy a stored file to a new name in the same folder.
     * Used when duplicating a record so each copy has an independent file.
     *
     * Returns the new path on success, or the original path on failure
     * (so callers always get a usable value; failure is logged not thrown).
     */
    public function copy(string $folder, ?string $sourcePath): string
    {
        if ($sourcePath === null || trim($sourcePath) === '') {
            return '';
        }

        $filename = basename($sourcePath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return $sourcePath;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $newName = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');

        return $this->disk() === 'aws_s3'
            ? $this->copyOnS3($folder, $filename, $newName) ?? $sourcePath
            : $this->copyLocal($folder, $filename, $newName) ?? $sourcePath;
    }

    private function copyLocal(string $folder, string $filename, string $newName): ?string
    {
        $rel = $this->folderRel($folder);
        $source = $this->localDiskPath($rel . $filename);
        $dest = $this->localDiskPath($rel . $newName);

        if (!is_file($source)) {
            return null;
        }

        if (!@copy($source, $dest)) {
            return null;
        }

        return $rel . $newName;
    }

    private function copyOnS3(string $folder, string $filename, string $newName): ?string
    {
        $s3 = $this->s3Client();
        if ($s3 === null) {
            return null;
        }

        $bucket = $this->settings()['aws_bucket'] ?? '';
        $sourceKey = $this->s3Key($folder, $filename);
        $destKey = $this->s3Key($folder, $newName);

        try {
            $s3->copyObject([
                'Bucket' => $bucket,
                'CopySource' => $bucket . '/' . $sourceKey,
                'Key' => $destKey,
            ]);
            return $destKey;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Delete a stored file. Null/empty path is a successful no-op.
     */
    public function delete(string $folder, ?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return true;
        }

        $filename = basename($path);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return false;
        }

        return $this->disk() === 'aws_s3'
            ? $this->deleteFromS3($folder, $filename)
            : $this->deleteLocal($folder, $filename);
    }

    /**
     * Resolve a stored path/filename to a full URL. Falls back to a
     * placeholder when the path is empty, the file is missing, or the
     * S3 configuration is incomplete.
     */
    public function url(?string $path, string $folder, ?string $default = null): string
    {
        $default = $default ?? self::DEFAULT_IMAGE;

        if ($path === null || trim($path) === '') {
            return base_url($default);
        }

        return $this->disk() === 'aws_s3'
            ? $this->urlForS3($path, $folder, $default)
            : $this->urlForLocal($path, $folder, $default);
    }

    /**
     * Check if a stored file exists on its disk.
     */
    public function exists(string $folder, ?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        if ($this->disk() === 'aws_s3') {
            return $this->existsInS3($folder, basename($path));
        }

        return is_file($this->localDiskPath($this->normalizeLocalKey($path, $folder)));
    }

    // ---------------------------------------------------------------------
    // Local disk
    // ---------------------------------------------------------------------

    private function uploadToLocal(UploadedFile $file, string $folder, string $name): array
    {
        $rel = $this->folderRel($folder);
        $target = $this->localDiskPath($rel);

        if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
            return [
                'error' => true,
                'path' => null,
                'disk' => 'local_server',
                'message' => 'Failed to create upload directory.',
            ];
        }

        try {
            $file->move($target, $name, true);
        } catch (Throwable $e) {
            return [
                'error' => true,
                'path' => null,
                'disk' => 'local_server',
                'message' => $e->getMessage(),
            ];
        }

        $this->compressIfImage($target . $name);

        return [
            'error' => false,
            'path' => $rel . $name,
            'disk' => 'local_server',
            'message' => null,
        ];
    }

    private function deleteLocal(string $folder, string $filename): bool
    {
        $full = $this->localDiskPath($this->folderRel($folder) . $filename);

        if (!is_file($full)) {
            return true;
        }

        return @unlink($full);
    }

    private function urlForLocal(string $path, string $folder, string $default): string
    {
        $key = $this->normalizeLocalKey($path, $folder);

        if (!is_file($this->localDiskPath($key))) {
            return base_url($default);
        }

        return base_url($key);
    }

    /**
     * On this project's deployments, FCPATH is the project root (index.php is
     * not nested under a `public/` subfolder) — stored keys are root-relative
     * and already include the `public/` segment, so no stripping is needed;
     * confirmed via FileService::* trace logs against the live test server
     * (FCPATH=".../edemand-test/", real file at FCPATH+"public/uploads/...").
     */
    private function localDiskPath(string $key): string
    {
        return FCPATH . $key;
    }

    /**
     * Tolerate filename-only and relative-path stored values.
     * Legacy paths like "languages/foo.png" use basename() + the folder's configured dir
     * so "languages/foo.png" with folder "language_image" resolves to
     * "public/uploads/languages/images/foo.png" rather than the wrong prefix.
     */
    private function normalizeLocalKey(string $path, string $folder): string
    {
        if (strpos($path, '/') === false) {
            return $this->folderRel($folder) . $path;
        }

        // Already a full relative path from web root.
        if (strpos($path, 'public/') === 0) {
            return $path;
        }

        // Legacy: stored as "subfolder/filename.ext" — strip the old prefix and
        // resolve using the folder's configured upload directory.
        return $this->folderRel($folder) . basename($path);
    }

    // ---------------------------------------------------------------------
    // S3 disk
    // ---------------------------------------------------------------------

    private function uploadToS3(UploadedFile $file, string $folder, string $name): array
    {
        $s3 = $this->s3Client();
        if ($s3 === null) {
            return [
                'error' => true,
                'path' => null,
                'disk' => 'aws_s3',
                'message' => 'AWS S3 configuration is incomplete.',
            ];
        }

        $bucket = $this->settings()['aws_bucket'] ?? '';
        $key = $this->s3Key($folder, $name);

        $this->compressIfImage($file->getTempName(), $file->getClientExtension());

        $stream = @fopen($file->getTempName(), 'rb');
        if ($stream === false) {
            return [
                'error' => true,
                'path' => null,
                'disk' => 'aws_s3',
                'message' => 'Failed to open uploaded file for streaming.',
            ];
        }

        try {
            $s3->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => $stream,
                'ContentType' => $file->getMimeType(),
            ]);
        } catch (Throwable $e) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            return [
                'error' => true,
                'path' => null,
                'disk' => 'aws_s3',
                'message' => $e->getMessage(),
            ];
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        return [
            'error' => false,
            'path' => $key,
            'disk' => 'aws_s3',
            'message' => null,
        ];
    }

    private function deleteFromS3(string $folder, string $filename): bool
    {
        $s3 = $this->s3Client();
        if ($s3 === null) {
            return false;
        }

        $bucket = $this->settings()['aws_bucket'] ?? '';

        try {
            $s3->deleteObject([
                'Bucket' => $bucket,
                'Key' => $this->s3Key($folder, $filename),
            ]);
            return true;
        } catch (S3Exception | Throwable $e) {
            return false;
        }
    }

    private function existsInS3(string $folder, string $filename): bool
    {
        $s3 = $this->s3Client();
        if ($s3 === null) {
            return false;
        }

        $bucket = $this->settings()['aws_bucket'] ?? '';

        try {
            return $s3->doesObjectExist($bucket, $this->s3Key($folder, $filename));
        } catch (Throwable $e) {
            return false;
        }
    }

    private function urlForS3(string $path, string $folder, string $default): string
    {
        $awsUrl = rtrim((string) ($this->settings()['aws_url'] ?? ''), '/');
        if ($awsUrl === '') {
            return base_url($default);
        }

        $filename = basename($path);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return base_url($default);
        }

        return $awsUrl . '/' . $this->s3Key($folder, $filename);
    }

    private function s3Key(string $folder, string $filename): string
    {
        $folder = preg_replace('#[^a-zA-Z0-9_\-/]#', '', trim($folder, '/'));
        return ($folder === '' ? '' : $folder . '/') . $filename;
    }

    private function s3Client(): ?S3Client
    {
        if ($this->s3Cache !== null) {
            return $this->s3Cache;
        }

        $s = $this->settings();
        $key = (string) ($s['aws_access_key_id'] ?? '');
        $secret = (string) ($s['aws_secret_access_key'] ?? '');
        $bucket = (string) ($s['aws_bucket'] ?? '');
        $region = (string) ($s['aws_default_region'] ?? 'us-east-1');

        if ($key === '' || $secret === '' || $bucket === '') {
            return null;
        }

        $this->s3Cache = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => ['key' => $key, 'secret' => $secret],
        ]);

        return $this->s3Cache;
    }

    // ---------------------------------------------------------------------
    // Settings / disk resolution
    // ---------------------------------------------------------------------

    private function disk(): string
    {
        if ($this->diskCache !== null) {
            return $this->diskCache;
        }

        $cache = service('cache');
        $cached = $cache->get(self::CACHE_KEY_DISK);
        if ($cached === 'aws_s3' || $cached === 'local_server') {
            return $this->diskCache = $cached;
        }

        $row = Database::connect()
            ->table('settings')
            ->select('value')
            ->where('variable', 'storage_disk')
            ->get()
            ->getRowArray();

        $value = $row['value'] ?? '';
        $this->diskCache = $value === 'aws_s3' ? 'aws_s3' : 'local_server';

        $cache->save(self::CACHE_KEY_DISK, $this->diskCache, self::CACHE_TTL);

        return $this->diskCache;
    }

    private function settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        $cache = service('cache');
        $cached = $cache->get(self::CACHE_KEY_SETTINGS);
        if (is_array($cached)) {
            return $this->settingsCache = $cached;
        }

        $row = Database::connect()
            ->table('settings')
            ->select('value')
            ->where('variable', 'general_settings')
            ->get()
            ->getRowArray();

        $decoded = $row ? json_decode((string) $row['value'], true) : null;
        $this->settingsCache = is_array($decoded) ? $decoded : [];

        $cache->save(self::CACHE_KEY_SETTINGS, $this->settingsCache, self::CACHE_TTL);

        return $this->settingsCache;
    }

    /**
     * Invalidate cached disk/settings lookups. Call after admin updates
     * the storage_disk or general_settings rows so the next request sees
     * fresh values without waiting for the TTL.
     */
    public function clearCache(): void
    {
        $cache = service('cache');
        $cache->delete(self::CACHE_KEY_DISK);
        $cache->delete(self::CACHE_KEY_SETTINGS);

        $this->diskCache = null;
        $this->settingsCache = null;
        $this->s3Cache = null;
    }

    private function folderRel(string $folder): string
    {
        return $this->folderMap[$folder] ?? ('public/uploads/' . trim($folder, '/') . '/');
    }

    /**
     * Re-encode a raster image in place to reduce file size using CI4's
     * image service. No-op for non-image extensions, missing files, or
     * handler failures (original file is preserved on error).
     *
     * For temp uploads where the on-disk extension is absent, an explicit
     * hint can be passed via $extensionHint.
     */
    private function compressIfImage(string $fullPath, ?string $extensionHint = null): void
    {
        if (!is_file($fullPath)) {
            return;
        }

        $settings = $this->settings();
        if ((int) ($settings['image_compression_preference'] ?? 1) === 0) {
            return;
        }

        $ext = $extensionHint !== null
            ? strtolower($extensionHint)
            : strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (!in_array($ext, self::COMPRESSIBLE_EXT, true)) {
            return;
        }

        $quality = (int) ($settings['image_compression_quality'] ?? self::COMPRESS_QUALITY_DEFAULT);
        if ($quality < 1 || $quality > 100) {
            $quality = self::COMPRESS_QUALITY_DEFAULT;
        }

        try {
            service('image')
                ->withFile($fullPath)
                ->save($fullPath, $quality);
        } catch (Throwable $e) {
            // Keep original file on failure.
        }
    }
}
