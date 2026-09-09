<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\CustomIonAuth;
/**
 * Protected Route Filter
 * 
 * This filter combines authentication check and input sanitization for protected routes.
 * It first checks if the user is logged in, then sanitizes input data.
 * 
 * @package App\Filters
 */
class ProtectedRouteFilter implements FilterInterface
{
    /**
     * Check authentication and sanitize input before controller execution
     * 
     * @param RequestInterface $request The incoming request
     * @param array|null $arguments Filter arguments
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get the current URI to check for public routes
        $uri = $request->getUri();
        $path = $uri->getPath();

        // Define public routes that don't require authentication
        $publicRoutes = [
            '/admin/login',
            '/partner/login',
            '/handyman/login',
            '/auth/logout',
            '/admin/forgot-password',
            '/customer_privacy_policy',
            '/provider-details',
            '/auth/check_number',
            '/auth/check_number_for_forgot_password',
            '/auth/reset_password_otp',
            '/auth/send_sms_otp',
            '/auth/verify_sms_otp',
            '/payment-form',
            '/payment',
            '/update_subscription_status',
            '/cancle_elapsed_time_order',
            '/lang',
            '/unauthorised',
            '/migration',
            '/partner/api',
            '/api'
        ];

        // Check if current route is public
        foreach ($publicRoutes as $publicRoute) {
            if (strpos($path, $publicRoute) === 0) {
                // This is a public route, only sanitize input
                $this->sanitizeInput($request);
                return $request;
            }
        }

        // First, check authentication for protected routes
        $ionAuth = new CustomIonAuth();

        // Check if user is logged in
        if (!$ionAuth->loggedIn()) {
            // Determine the appropriate login page based on the route
            if (strpos($path, '/admin/') === 0) {
                return redirect()->to('/admin/login');
            } elseif (strpos($path, '/partner/') === 0) {
                return redirect()->to('/partner/login');
            } elseif (strpos($path, '/handyman/') === 0) {
                return redirect()->to('/handyman/login');
            } else {
                return redirect()->to('/admin/login/');
            }
        }

        // Partner panel: when provider app maintenance mode is active (current time between start and end),
        // only allow the maintenance page; redirect all other partner routes to it.
        // Uses same logic as get_settings in SettingsApiController (is_provider_app_maintenance_mode helper).
        // Path may have or lack leading slash depending on CI config.
        $pathNorm = ltrim($path, '/');
        if (strpos($pathNorm, 'partner/') === 0 && strpos($pathNorm, 'partner/maintenance') !== 0) {
            helper('function');
            $maintenance = is_provider_app_maintenance_mode();
            if (!empty($maintenance['active'])) {
                return redirect()->to(base_url('partner/maintenance'));
            }
        }

        // Partner panel only (not partner API): if phone/country_code changed in DB since login (e.g. by admin),
        // log the user out so they must sign in again. All at runtime via session snapshot vs DB.
        if (strpos($pathNorm, 'partner/') === 0 && strpos($pathNorm, 'partner/api') !== 0) {
            $userId = $ionAuth->getUserId();
            if ($userId) {
                $session = \Config\Services::session();
                $sessionPhone = $session->get('partner_phone_at_login');
                $sessionCc = $session->get('partner_country_code_at_login');
                if ($sessionPhone !== null || $sessionCc !== null) {
                    helper('function');
                    $userRow = fetch_details('users', ['id' => $userId], ['phone', 'country_code']);
                    if (!empty($userRow[0])) {
                        $dbPhone = trim((string)($userRow[0]['phone'] ?? ''));
                        $dbCc = trim((string)($userRow[0]['country_code'] ?? ''));
                        if ($dbCc !== '' && $dbCc[0] !== '+') {
                            $dbCc = '+' . $dbCc;
                        }
                        $sessCc = trim((string)($sessionCc ?? ''));
                        if ($sessCc !== '' && $sessCc[0] !== '+') {
                            $sessCc = '+' . $sessCc;
                        }
                        if ($dbPhone !== trim((string)($sessionPhone ?? '')) || $dbCc !== $sessCc) {
                            helper('session');
                            safe_destroy_session();
                            return redirect()->to('/partner/login');
                        }
                    }
                }
            }
        }

        // User is logged in, now sanitize input data
        $this->sanitizeInput($request);

        // Allow the request to continue
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

    /**
     * Sanitize input data from POST and GET requests
     *
     * getPost, getGet, setGlobal come from RequestTrait (used by IncomingRequest).
     * RequestInterface does not declare them, so we assert the concrete type for static analysis.
     *
     * @param RequestInterface $request The request to sanitize (IncomingRequest at runtime)
     * @return void
     */
    private function sanitizeInput(RequestInterface $request)
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $req */
        $req = $request;

        // Sanitize POST data
        $post = $req->getPost();
        foreach ($post as $key => $value) {
            if (is_string($value)) {
                $post[$key] = $this->sanitizeInputValue($value);
            }
        }
        $req->setGlobal('post', $post);

        // Sanitize GET data
        $get = $req->getGet();
        foreach ($get as $key => $value) {
            if (is_string($value)) {
                $get[$key] = $this->sanitizeInputValue($value);
            }
        }
        $req->setGlobal('get', $get);
    }

    /**
     * Sanitize individual input value to prevent XSS attacks
     * 
     * This method removes dangerous HTML/JavaScript while allowing
     * safe formatting tags for rich content.
     * 
     * @param string $input The input value to sanitize
     * @return string The sanitized input
     */
    private function sanitizeInputValue($input)
    {
        // First, remove any script tags and event handlers completely
        // Remove script tags and their content
        $input = preg_replace('#<script[^>]*>.*?</script>#is', '', $input);

        // Remove event handlers (onclick, onerror, etc.)
        $input = preg_replace('#\s*on\w+\s*=\s*["\'][^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*on\w+\s*=\s*[^\s>]+#i', '', $input);

        // Remove javascript: and vbscript: protocols
        $input = preg_replace('#javascript:#i', '', $input);
        $input = preg_replace('#vbscript:#i', '', $input);
        $input = preg_replace('#data:text/html#i', '', $input);

        // Remove iframe tags completely (they can be dangerous)
        $input = preg_replace('#<iframe[^>]*>.*?</iframe>#is', '', $input);

        // Remove object and embed tags (can execute code)
        $input = preg_replace('#<object[^>]*>.*?</object>#is', '', $input);
        $input = preg_replace('#<embed[^>]*>.*?</embed>#is', '', $input);

        // Allow only safe HTML tags for formatting
        // Removed iframe from allowed tags for security
        $allowedTags = '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><span><div>';

        // Strip unwanted tags while allowing specific safe ones
        $input = strip_tags($input, $allowedTags);

        // Remove any remaining dangerous attributes from allowed tags
        // This removes style attributes that might contain expressions
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*expression[^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*javascript[^"\']*["\']#i', '', $input);

        // Clean up any remaining dangerous patterns
        $input = preg_replace('#<[^>]*\s*on\w+\s*=[^>]*>#i', '', $input);

        return $input;
    }
}
