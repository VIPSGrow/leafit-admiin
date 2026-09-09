<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Injects csrfName / csrfHash into JSON response bodies.
 *
 * Replaces the per-call boilerplate that previously trailed every
 * successResponse() / ErrorResponse() / NoPermission() invocation:
 *
 *     successResponse($msg, false, $data, [], 200, csrf_token(), csrf_hash());
 *
 * With this `after` filter mounted on admin routes, controllers can use
 * App\Support\JsonResponse::success/error and omit the csrf args entirely.
 *
 * Skips:
 *   - Non-JSON responses (HTML views, redirects, file downloads).
 *   - Bodies that already contain csrfName/csrfHash (legacy helper calls
 *     continue to work unchanged during the rolling migration).
 */
class CsrfResponseFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // no-op
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        if (stripos($contentType, 'application/json') === false) {
            return $response;
        }

        $body = $response->getBody();
        if ($body === '' || $body === null) {
            return $response;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return $response;
        }

        // Don't overwrite tokens already set by legacy helpers mid-migration.
        if (array_key_exists('csrfName', $payload) && array_key_exists('csrfHash', $payload)) {
            return $response;
        }

        $payload['csrfName'] = csrf_token();
        $payload['csrfHash'] = csrf_hash();

        return $response->setJSON($payload);
    }
}
