<?php

namespace App\Services\utility;

use App\Models\user_permissions_model;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * PermissionService — centralised permission logic with caching.
 *
 * This is the single source of truth for reading, writing, and
 * invalidating user permissions. Controllers and helpers should
 * delegate to this service instead of touching the model or DB
 * directly for permission operations.
 *
 * Cache shape per user:
 *   ['role' => string|null, 'matrix' => array]
 *   Both role and matrix share one cache entry and one DB read.
 *
 * Super-admin optimisation:
 *   - can() / has() return true immediately (no matrix built).
 *   - permissionsFor() returns a shared "all ones" matrix (built once).
 *
 * Public API:
 *   Read  — can(), has(), permissionsFor(), roleFor(), isSuperAdmin()
 *   Write — setForUser(), deleteForUser(), buildFromRequest()
 *   Cache — forget()
 */
class PermissionService
{
    // ----------------------------------------------------------------
    // Dependencies
    // ----------------------------------------------------------------

    /** @var user_permissions_model  Model used only inside this service. */
    protected $model;

    /** @var \CodeIgniter\Cache\CacheInterface  CI4 cache instance. */
    protected $cache;

    /** @var array  Module definitions from Config\Edemand::$permissions. */
    protected $modules;

    /**
     * Shared "all ones" matrix for super admins.
     * Built lazily on first use and reused for every super admin.
     * Avoids rebuilding a full matrix per super-admin user.
     *
     * @var array|null
     */
    private $superAdminMatrix = null;

    // ----------------------------------------------------------------
    // Role constants — avoid magic strings throughout the codebase.
    // ----------------------------------------------------------------

    /** Super admin role value stored in the user_permissions table. */
    const ROLE_SUPER_ADMIN = '1';

    // ----------------------------------------------------------------
    // Cache settings
    // ----------------------------------------------------------------

    /** Cache key prefix so permission keys are easy to identify. */
    const CACHE_PREFIX = 'permissions_user_';

    /** Default TTL in seconds (15 minutes). */
    const CACHE_TTL = 900;

    // ----------------------------------------------------------------
    // The four CRUD action types used throughout the system.
    // ----------------------------------------------------------------
    const ACTIONS = ['create', 'read', 'update', 'delete'];

    // ----------------------------------------------------------------
    // Constructor
    // ----------------------------------------------------------------

    public function __construct()
    {
        $this->model   = new user_permissions_model();
        $this->cache   = \Config\Services::cache();
        $this->modules = (new \Config\Edemand())->permissions;
    }

    // ================================================================
    //  READ — cached permission checks
    // ================================================================

    /**
     * Check if a user is allowed to perform an action on a module.
     *
     * Short-circuits for super admin — returns true immediately
     * without building or inspecting the full permission matrix.
     *
     * Usage:
     *   $permissionService->can($userId, 'create', 'sliders')
     *
     * @param  int    $userId  The user to check.
     * @param  string $action  One of: create, read, update, delete.
     * @param  string $module  Module key from Config\Edemand (e.g. 'orders').
     * @return bool
     */
    public function can(int $userId, string $action, string $module): bool
    {
        // Super admin is allowed everything — no matrix needed.
        $payload = $this->resolvePayload($userId);
        if ($payload['role'] === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Regular user — check the matrix.
        $matrix = $payload['matrix'];
        return isset($matrix[$action][$module])
            && (int) $matrix[$action][$module] === 1;
    }

    /**
     * Alias of can().  Reads naturally in some contexts:
     *   "has delete permission on orders"
     *
     * @param  int    $userId
     * @param  string $action
     * @param  string $module
     * @return bool
     */
    public function has(int $userId, string $action, string $module): bool
    {
        return $this->can($userId, $action, $module);
    }

    /**
     * Return the full permission matrix for a user.
     *
     * Structure:
     *   [
     *       'create' => ['categories' => 1, 'orders' => 0, ...],
     *       'read'   => [...],
     *       'update' => [...],
     *       'delete' => [...],
     *   ]
     *
     * For super admin this returns a shared "all ones" matrix
     * (built once in memory, never per-user from DB).
     *
     * @param  int   $userId
     * @return array
     */
    public function permissionsFor(int $userId): array
    {
        $payload = $this->resolvePayload($userId);

        // Super admin — return the shared "all ones" matrix.
        if ($payload['role'] === self::ROLE_SUPER_ADMIN) {
            return $this->getSuperAdminMatrix();
        }

        return $payload['matrix'];
    }

    // ================================================================
    //  WRITE — permission storage (model only used here)
    // ================================================================

    /**
     * Save or update permissions for a user.
     *
     * For super admin (role "1"), stored permissions are set to NULL
     * because a super admin is implicitly allowed everything.
     *
     * @param  int    $userId      The user to save permissions for.
     * @param  string $role        ROLE_SUPER_ADMIN for super admin, other values for regular admin.
     * @param  array  $permissions The full matrix (create/read/update/delete arrays).
     * @return bool   True on success, false on failure.
     */
    public function setForUser(int $userId, string $role, array $permissions): bool
    {
        // Super admin gets NULL permissions (all allowed implicitly).
        $encoded = ($role === self::ROLE_SUPER_ADMIN) ? null : json_encode($permissions);

        // Check if a row already exists for this user.
        $existing = $this->model
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            // Update existing row.
            $success = $this->model->update($existing['id'], [
                'role'        => $role,
                'permissions' => $encoded,
            ]);
        } else {
            // Insert new row.
            $success = $this->model->insert([
                'user_id'     => $userId,
                'role'        => $role,
                'permissions' => $encoded,
            ]);
        }

        // Invalidate cache so next read is fresh.
        $this->forget($userId);

        return (bool) $success;
    }

    /**
     * Delete all permissions for a user.
     *
     * Call this when the system user is being removed.
     *
     * @param  int  $userId
     * @return bool True on success.
     */
    public function deleteForUser(int $userId): bool
    {
        $success = $this->model->where('user_id', $userId)->delete();

        // Clear cache — no permissions to serve anymore.
        $this->forget($userId);

        return (bool) $success;
    }

    /**
     * Build a permission matrix from a POST request.
     *
     * This replicates the logic that was previously duplicated in
     * System_users::permit() and System_users::edit_permit().
     *
     * POST keys follow the pattern:
     *   {module}_{action}          (for "add" form)
     *   {module}_{action}_{suffix} (for "edit" form, suffix = "edit")
     *
     * @param  IncomingRequest $request  The incoming request.
     * @param  string           $suffix   Optional suffix appended to POST keys (e.g. "edit").
     * @return array  The permission matrix ready for setForUser().
     */
    public function buildFromRequest(IncomingRequest $request, string $suffix = ''): array
    {
        $create = [];
        $read   = [];
        $update = [];
        $delete = [];

        // Build the POST key suffix part (e.g. "_create" or "_create_edit").
        $suffixPart = $suffix !== '' ? '_' . $suffix : '';

        foreach ($this->modules as $module => $allowedActions) {
            if (in_array('create', $allowedActions)) {
                $key = $module . '_create' . $suffixPart;
                $create[$module] = ($request->getPost($key) === 'true') ? 1 : 0;
            }
            if (in_array('read', $allowedActions)) {
                $key = $module . '_read' . $suffixPart;
                $read[$module] = ($request->getPost($key) === 'true') ? 1 : 0;
            }
            if (in_array('update', $allowedActions)) {
                $key = $module . '_update' . $suffixPart;
                $update[$module] = ($request->getPost($key) === 'true') ? 1 : 0;
            }
            if (in_array('delete', $allowedActions)) {
                $key = $module . '_delete' . $suffixPart;
                $delete[$module] = ($request->getPost($key) === 'true') ? 1 : 0;
            }
        }

        return [
            'create' => $create,
            'read'   => $read,
            'update' => $update,
            'delete' => $delete,
        ];
    }

    // ================================================================
    //  CACHE — invalidation
    // ================================================================

    /**
     * Forget (invalidate) cached permissions for a specific user.
     *
     * Call this whenever permissions are saved, updated, or deleted.
     *
     * @param  int  $userId
     * @return void
     */
    public function forget(int $userId): void
    {
        $this->cache->delete(self::CACHE_PREFIX . $userId);
    }

    // ================================================================
    //  HELPERS — module definitions and role queries
    // ================================================================

    /**
     * Return the module definitions from Config\Edemand.
     *
     * Useful when a controller needs to list available modules
     * (e.g. the system-user form).
     *
     * @return array
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get the role stored for a given user.
     *
     * Uses the same cached payload as permissionsFor() / can().
     * No separate DB call — role and matrix share one cache entry.
     *
     * Returns null if no permission row exists for this user.
     *
     * @param  int         $userId
     * @return string|null The role value (e.g. ROLE_SUPER_ADMIN for super admin).
     */
    public function roleFor(int $userId): ?string
    {
        $payload = $this->resolvePayload($userId);
        return $payload['role'];
    }

    /**
     * Check if a user has super-admin privileges (ROLE_SUPER_ADMIN).
     *
     * Uses cache — no direct DB call.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isSuperAdmin(int $userId): bool
    {
        return $this->roleFor($userId) === self::ROLE_SUPER_ADMIN;
    }

    // ================================================================
    //  INTERNAL — cache resolution (single entry point)
    // ================================================================

    /**
     * Resolve the cached payload for a user.
     *
     * This is the single gateway to cached data. Every public read
     * method (can, has, permissionsFor, roleFor, isSuperAdmin) calls
     * this instead of touching the cache or DB directly.
     *
     * Payload shape:
     *   ['role' => string|null, 'matrix' => array]
     *
     * On cache hit  — returns the stored payload immediately.
     * On cache miss — loads from DB, builds the payload, caches it.
     *
     * For super admin the cached matrix is an empty array because
     * can() short-circuits on role and permissionsFor() returns the
     * shared super-admin matrix. This avoids storing a per-user
     * "all ones" matrix in cache for every super admin.
     *
     * @param  int   $userId
     * @return array The payload ['role' => ..., 'matrix' => ...].
     */
    private function resolvePayload(int $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . $userId;

        // 1. Try cache first.
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached) && array_key_exists('role', $cached)) {
            return $cached;
        }

        // 2. Cache miss — load from DB.
        $payload = $this->loadFromDb($userId);

        // 3. Store in cache for next time.
        $this->cache->save($cacheKey, $payload, self::CACHE_TTL);

        return $payload;
    }

    // ================================================================
    //  INTERNAL — DB loading & normalisation (private)
    // ================================================================

    /**
     * Load raw permissions from DB and build a normalised payload.
     *
     * Returns a payload containing both role and matrix so they
     * can be cached together in one entry.
     *
     * For super admin the matrix is empty (not built) because
     * callers use the shared super-admin matrix instead.
     *
     * @param  int   $userId
     * @return array Payload: ['role' => string|null, 'matrix' => array].
     */
    private function loadFromDb(int $userId): array
    {
        // Fetch the single permission row for this user.
        $row = $this->model
            ->select('role, permissions')
            ->where('user_id', $userId)
            ->first();

        // No row at all — unknown user, no role, all-zeros matrix.
        if (empty($row)) {
            return [
                'role'   => null,
                'matrix' => $this->buildDefaultMatrix(),
            ];
        }

        // -------------------------------------------------------
        // Super admin (ROLE_SUPER_ADMIN) — skip matrix build.
        // The shared super-admin matrix is used by callers instead.
        // We store an empty matrix in cache to keep the payload
        // lightweight; permissionsFor() substitutes the shared one.
        // -------------------------------------------------------
        if ($row['role'] === self::ROLE_SUPER_ADMIN) {
            return [
                'role'   => self::ROLE_SUPER_ADMIN,
                'matrix' => [],
            ];
        }

        // -------------------------------------------------------
        // Regular user — build and normalise the matrix from DB.
        // -------------------------------------------------------
        $matrix = $this->buildDefaultMatrix();

        if (!empty($row['permissions'])) {
            $stored = json_decode($row['permissions'], true);
            if (is_array($stored)) {
                $matrix = $this->mergeStoredPermissions($matrix, $stored);
            }
        }

        return [
            'role'   => $row['role'],
            'matrix' => $matrix,
        ];
    }

    /**
     * Build a matrix with every module/action set to 0.
     *
     * @return array
     */
    private function buildDefaultMatrix(): array
    {
        $matrix = [
            'create' => [],
            'read'   => [],
            'update' => [],
            'delete' => [],
        ];

        foreach ($this->modules as $module => $allowedActions) {
            foreach (self::ACTIONS as $action) {
                if (in_array($action, $allowedActions)) {
                    $matrix[$action][$module] = 0;
                }
            }
        }

        return $matrix;
    }

    /**
     * Merge stored (DB) permissions into the default matrix.
     *
     * Normalises legacy values like "yes"/"no", booleans, and
     * string "1"/"0" into integer 1 or 0.
     *
     * @param  array $matrix  Default matrix (all 0s).
     * @param  array $stored  Decoded JSON from the DB.
     * @return array
     */
    private function mergeStoredPermissions(array $matrix, array $stored): array
    {
        foreach ($stored as $action => $modulePerms) {
            if (!is_array($modulePerms)) {
                continue;
            }
            foreach ($modulePerms as $module => $value) {
                // Normalise various truthy representations to 1.
                $normalised = ($value === 'yes' || $value === '1' || $value === 1 || $value === true)
                    ? 1
                    : 0;

                $matrix[$action][$module] = $normalised;
            }
        }

        return $matrix;
    }

    /**
     * Get the shared super-admin matrix (all ones).
     *
     * Built lazily on first call and stored in memory for the
     * lifetime of this service instance. Every super admin reuses
     * the same matrix — no per-user build or per-user cache entry.
     *
     * @return array
     */
    private function getSuperAdminMatrix(): array
    {
        // Build once, reuse forever within this request/instance.
        if ($this->superAdminMatrix === null) {
            $matrix = $this->buildDefaultMatrix();

            // Flip every 0 to 1.
            foreach ($matrix as $action => &$modules) {
                foreach ($modules as $module => &$value) {
                    $value = 1;
                }
            }

            $this->superAdminMatrix = $matrix;
        }

        return $this->superAdminMatrix;
    }
}
