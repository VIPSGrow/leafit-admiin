<?php
function successResponse($message = "Success", $error = false, $data = null, $customData = [], $code = 200, $csrfName = null, $csrfHash = null)
{
    $response = [
        'error'     => $error,
        'message'   => labels($message, $message),
        'data'      => $data,
        'code'      => $code,
        'csrfName'  => $csrfName,
        'csrfHash'  => $csrfHash,
    ];

    return response_helper()->setJSON(array_merge($response, $customData));
}
// function ErrorResponse($message = "Success", $error = false, $data = null, $customData = [], $code = 200, $csrfName = null, $csrfHash = null)
// {
//     $response = [
//         'error'     => $error,
//         'message'   => labels($message, $message),
//         'data'      => $data,
//         'code'      => $code,
//         'csrfName'  => $csrfName,
//         'csrfHash'  => $csrfHash,
//     ];

//     return response_helper()->setJSON(array_merge($response, $customData));
// }

function ErrorResponse($message = "Success", $error = false, $data = null, $customData = [], $code = 200, $csrfName = null, $csrfHash = null)
{
    // Handle array of errors - return them as a separate 'errors' field for step-by-step display
    // This allows the frontend to show each error individually instead of one long message
    if (is_array($message)) {
        // Process array errors: handle both indexed arrays and associative arrays (from validation)
        $errorArray = [];
        $labelMessage = '';

        // Check if it's an associative array (from CodeIgniter validation errors)
        $isAssociative = array_keys($message) !== range(0, count($message) - 1);

        if ($isAssociative) {
            // Associative array: convert to indexed array of error messages
            foreach ($message as $field => $errorMsg) {
                $errorArray[] = $errorMsg;
            }
            // Create a summary message for backward compatibility
            $labelMessage = implode(", ", $errorArray);
        } else {
            // Indexed array: use as is
            $errorArray = $message;
            $labelMessage = implode(", ", $errorArray);
        }

        $response = [
            'error'     => $error,
            'message'   => $labelMessage, // Keep for backward compatibility
            'errors'    => $errorArray,   // New field for step-by-step display
            'data'      => $data,
            'code'      => $code,
            'csrfName'  => $csrfName,
            'csrfHash'  => $csrfHash,
        ];
    } else {
        // Single error message - return as normal
        $response = [
            'error'     => $error,
            'message'   => $message,
            'data'      => $data,
            'code'      => $code,
            'csrfName'  => $csrfName,
            'csrfHash'  => $csrfHash,
        ];
    }
    return response_helper()->setJSON(array_merge($response, $customData));
}
/**
 * Phase 7 successors to successResponse() / ErrorResponse() / NoPermission().
 *
 * Drop the 7-arg positional noise. csrfName/csrfHash are injected by
 * App\Filters\CsrfResponseFilter (after filter mounted on admin/*).
 * Response envelope shape unchanged: error, message, data, code [, errors].
 */
function JsonSuccess(string $message = 'Success', $data = null, array $extra = [], int $code = 200)
{
    $payload = [
        'error'   => false,
        'message' => labels($message, $message),
        'data'    => $data,
        'code'    => $code,
    ];

    return \Config\Services::response()->setJSON(array_merge($payload, $extra));
}

function JsonError($message = 'Error', $data = null, array $extra = [], int $code = 200)
{
    $payload = [
        'error' => true,
        'data'  => $data,
        'code'  => $code,
    ];

    if (is_array($message)) {
        $errorList = array_values($message);
        $payload['message'] = implode(', ', $errorList);
        $payload['errors']  = $errorList;
    } else {
        $payload['message'] = $message;
    }

    return \Config\Services::response()->setJSON(array_merge($payload, $extra));
}

function JsonNoPermission(string $message = "Sorry! You're not permitted to take this action", int $code = 200)
{
    return JsonError($message, null, [], $code);
}

function NoPermission($message = "Sorry! You're not permitted to take this action", $error = true, $data = null, $customData = [], $code = 200, $csrfName = null, $csrfHash = null)
{
    $response = [
        'error'     => $error,
        'message'   => $message,
        'data'      => $data,
        'code'      => $code,
        'csrfName'  => $csrfName,
        'csrfHash'  => $csrfHash,
    ];

    return response_helper()->setJSON(array_merge($response, $customData));
}
function demoModeNotAllowed()
{
    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
        $response = [
            'error'     => true,
            'message'   => DEMO_MODE_ERROR,
            'csrfName'  => csrf_token(),
            'csrfHash'  => csrf_hash(),
        ];

        return $response;
        return response_helper()->setJSON($response);
    } else {

        return ['error' => false];
    }
}

function ApiErrorResponse($message = "Success", $error = false, $data = null)
{
    $response = [
        'error'     => $error,
        'message'   => $message,
        'data'      => $data,

    ];

    return response_helper()->setJSON(array_merge($response));
}

/**
 * Central helper to resolve a user id for permission checks.
 * We accept an explicit ID, fall back to the current session, and finally IonAuth.
 * This keeps permission helpers usable from both controllers and background jobs.
 */
function resolve_permission_user_id($userId = null)
{
    if (!empty($userId)) {
        return $userId;
    }

    $session = \Config\Services::session();
    if ($session && $session->get('user_id')) {
        return $session->get('user_id');
    }

    $ionAuth = new \App\Libraries\CustomIonAuth();
    if ($ionAuth->loggedIn()) {
        $user = $ionAuth->user()->row();
        return $user ? $user->id : null;
    }

    return null;
}

/**
 * Lightweight boolean helper so we can gate actions with a single call.
 * Example: has_permission('delete', 'send_notification', $this->userId).
 */
function has_permission($action, $module, $userId = null)
{
    $resolvedUserId = resolve_permission_user_id($userId);
    if (empty($resolvedUserId)) {
        return false;
    }

    $permissions = get_permission($resolvedUserId);
    return isset($permissions[$action][$module]) && (int)$permissions[$action][$module] === 1;
}

/**
 * Full guard that mirrors ErrorResponse/SuccessResponse behaviours.
 * Returns true on success so callers can keep their flow, otherwise a JSON response.
 */
function enforce_permission($action, $module, $userId = null, $messageKey = 'NO_PERMISSION_TO_TAKE_THIS_ACTION')
{
    if (has_permission($action, $module, $userId)) {
        return true;
    }

    return ErrorResponse(
        labels($messageKey, 'Sorry, you do not have permission to take this action'),
        true,
        [],
        [],
        403,
        csrf_token(),
        csrf_hash()
    );
}

/**
 * Redirect helper for read guards. Controllers can do:
 * $guard = enforce_permission_or_redirect('read', 'send_notification');
 * if ($guard !== true) { return $guard; }
 */
function enforce_permission_or_redirect($action, $module, $userId = null, $redirectTo = 'admin/dashboard', $messageKey = 'NO_PERMISSION_TO_TAKE_THIS_ACTION')
{
    if (has_permission($action, $module, $userId)) {
        return true;
    }

    $message = labels($messageKey, 'Sorry, you do not have permission to take this action');
    $session = \Config\Services::session();
    if ($session) {
        // Align with existing toast message UX so admins see immediate feedback.
        $_SESSION['toastMessage'] = $message;
        $_SESSION['toastMessageType'] = 'error';
        $session->markAsFlashdata('toastMessage');
        $session->markAsFlashdata('toastMessageType');
    }

    return redirect()->to(base_url($redirectTo));
}
function log_the_responce($message = "something went wrong", $controller = false, $level = 'error')
{
    $request = \Config\Services::request();

    // Build the multi-line formatted log message
    $lines = [];

    // First line: Controller and error message
    if ($controller) {
        $lines[] = "$controller";
    } else {
        $lines[] = $message;
    }

    // Second line: Line number (if exception) or custom message
    if (is_object($message) && method_exists($message, 'getLine')) {
        $lines[] = "At Line : " . $message->getLine();
    } else {
        $lines[] = "At Line : -"; // Default or you can customize this
    }

    // Get HTTP method and full URL path
    $method = $request->getMethod();
    $path = $request->getUri()->getPath();

    // Get user token from Authorization header
    $userToken = $request->getHeaderLine('Authorization');
    if (empty($userToken)) {
        $userToken = $request->getGet('token') ?? $request->getPost('token') ?? 'Not provided';
    }
    $lines[] = "User Token : $userToken";

    // Add full URL including domain
    $baseUrl = base_url();
    // Remove any duplicate paths
    if (strpos($path, '/edemand') === 0) {
        // If path already starts with /edemand, we need to avoid duplication
        $path = substr($path, strlen('/edemand'));
    }

    $fullUrl = rtrim($baseUrl, '/') . $path;
    $lines[] = "Base url : $fullUrl";
    $lines[] = "Method : $method";

    // Parameters
    $params = json_encode(array_merge($request->getGet(), $request->getPost()));
    $lines[] = "Params : $params";

    // Join all lines with line breaks
    $content = implode("\n", $lines);

    // Log with the specified level
    log_message($level, $content);
}

if (!function_exists('ApiSuccess')) {
    function ApiSuccess(string $message = 'Success', $data = null, array $extra = [], int $code = 200): \CodeIgniter\HTTP\Response
    {
        $body = ['error' => false, 'message' => labels($message, $message)];
        if ($data !== null) {
            $body['data'] = $data;
        }
        if (!empty($extra)) {
            $body = array_merge($body, $extra);
        }
        return \Config\Services::response()->setStatusCode($code)->setJSON($body);
    }
}

if (!function_exists('ApiError')) {
    function ApiError(string $message = 'Error', $data = null, array $extra = [], int $code = 200): \CodeIgniter\HTTP\Response
    {
        $body = ['error' => true, 'message' => labels($message, $message)];
        if ($data !== null) {
            $body['data'] = $data;
        }
        if (!empty($extra)) {
            $body = array_merge($body, $extra);
        }
        return \Config\Services::response()->setStatusCode($code)->setJSON($body);
    }
}
