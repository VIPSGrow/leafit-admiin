<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\CustomIonAuth;

/**
 * Admin Auth Filter
 *
 * Layered admin-role gate for the admin panel. This filter intentionally
 * does NOT duplicate the logged-in / input-sanitization logic already
 * owned by {@see ProtectedRouteFilter} (alias `protected`). It only adds
 * the admin-ROLE enforcement that was previously inlined in every admin
 * controller method as:
 *
 *     if (!$this->isLoggedIn || !$this->userIsAdmin) {
 *         return redirect('admin/login');
 *     }
 *
 * Behaviour is preserved exactly: a request that is not authenticated as
 * an admin is redirected to /admin/login (same destination the existing
 * `protected` filter uses for the not-logged-in case, and the same one
 * the inline checks used).
 *
 * Registered globally over `admin/*` so it covers all admin functionality,
 * not just the partner controllers.
 *
 * @package App\Filters
 */
class AdminAuthFilter implements FilterInterface
{
    /**
     * Public admin paths that must remain reachable without an admin
     * session. Mirrors the admin entries in ProtectedRouteFilter's
     * public route list so the two filters stay consistent and we never
     * redirect /admin/login back onto itself (infinite loop).
     *
     * @var string[]
     */
    private $publicAdminRoutes = [
        '/admin/login',
        '/admin/forgot-password',
        '/admin/settings/legal-pages/preview/',
        '/admin/settings/about-us-preview',
        '/admin/settings/contact-us-preview',
    ];

    /**
     * Enforce admin role before the controller runs.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = $request->getUri()->getPath();

        // Let public admin pages (login / forgot-password) through.
        foreach ($this->publicAdminRoutes as $publicRoute) {
            if (strpos($path, $publicRoute) === 0) {
                return $request;
            }
        }

        $ionAuth = new CustomIonAuth();

        // Not logged in OR not an admin -> same redirect the inline
        // controller guards and the `protected` filter already used.
        if (!$ionAuth->loggedIn() || !$ionAuth->isAdmin()) {
            return redirect()->to('/admin/login');
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
