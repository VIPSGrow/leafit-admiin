<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\CustomIonAuth;
use App\Services\utility\PermissionService;

/**
 * Permission Filter
 *
 * Route-arg authorization gate. Replaces the simple, single top-of-method
 * permission guard that was inlined in admin controllers as:
 *
 *     if (!is_permitted($this->creator_id, 'create', 'partner')) {
 *         return NoPermission();
 *     }
 *
 * Usage (Config\Filters::$filters key, alias + args):
 *
 *     'permission:create,partner' => ['before' => ['admin/partners/add_partner']]
 *
 * The user resolved here is the logged-in admin's id — identical to the
 * `$this->creator_id` (== `$this->userId`) the inline `is_permitted()`
 * calls used. Authorization itself goes through {@see PermissionService}
 * (cached, super-admin short-circuit) — the preferred path over the legacy
 * `is_permitted()` helper.
 *
 * On denial the response is exactly the inline behaviour: `NoPermission()`
 * (default message, no CSRF args), so the payload shape is unchanged.
 *
 * Branching permission checks (OR-logic, dynamically chosen action) are
 * intentionally NOT handled here — they stay explicit inline in the
 * controller, swapped to PermissionService::can().
 *
 * @package App\Filters
 */
class PermissionFilter implements FilterInterface
{
    /**
     * @param RequestInterface $request
     * @param array|null       $arguments  [0] => action, [1] => module
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Misconfigured route (no args) -> fail closed.
        if (empty($arguments) || count($arguments) < 2) {
            helper('ResponceServices');
            return NoPermission();
        }

        [$action, $module] = $arguments;

        $userId = (int) (new CustomIonAuth())->getUserId();

        if (!(new PermissionService())->can($userId, $action, $module)) {
            helper('ResponceServices');
            return NoPermission();
        }

        return $request;
    }

    /**
     * No post-processing needed.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
