<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Authentication Filter
 * 
 * This filter checks if the user is logged in before allowing access to protected routes.
 * If not logged in, it redirects to the appropriate login page based on the route.
 * 
 * @package App\Filters
 */
class AuthFilter implements FilterInterface
{
    /**
     * Check authentication before controller execution
     * 
     * @param RequestInterface $request The incoming request
     * @param array|null $arguments Filter arguments
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Initialize IonAuth for authentication check
        $ionAuth = new \App\Libraries\CustomIonAuth();

        // Check if user is logged in
        if (!$ionAuth->loggedIn()) {
            // Get the current URI to determine which login page to redirect to
            $uri = $request->getUri();
            $path = $uri->getPath();

            // Determine the appropriate login page based on the route
            if (strpos($path, '/admin/') === 0) {
                // Admin routes - redirect to admin login
                return redirect()->to('/admin/login');
            } elseif (strpos($path, '/partner/') === 0) {
                // Partner routes - redirect to partner login
                return redirect()->to('/partner/login');
            } else {
                // Default fallback - redirect to main login
                return redirect()->to('/admin/login/');
            }
        }

        // User is logged in, allow the request to continue
        return $request;
    }

    /**
     * After controller execution (not used for authentication)
     * 
     * @param RequestInterface $request The request
     * @param ResponseInterface $response The response
     * @param array|null $arguments Filter arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after controller execution
        return $response;
    }
}
