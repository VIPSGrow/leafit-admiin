<?php

namespace App\Controllers\Partner;

use App\Models\Notification_model;
use App\Models\Users_model;
use App\Services\NotificationTemplateService;
use CodeIgniter\I18n\Time;

/**
 * Partner Notifications (web panel).
 *
 * What this controller does:
 * - `index()`  : renders the "View all notifications" page for partners.
 * - `recent()` : returns the latest 5 notifications for the header dropdown.
 * - `list()`   : returns a paginated list for the table on the "View all" page.
 *
 * Why we keep it separate from the mobile API controllers:
 * - The web panel needs session-based auth (protected route group).
 * - The mobile apps use JWT auth and different response shapes.
 */
class Notifications extends Partner
{
    private Notification_model $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new Notification_model();
        helper('function');
        helper('ResponceServices');
    }

    /**
     * "View all notifications" page.
     */
    public function index()
    {
        // Keep the same guard style as other partner controllers.
        if (!($this->isLoggedIn && !$this->userIsAdmin)) {
            return redirect('partner/login');
        }

        // Only approved partners should access partner panel pages.
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }

        setPageInfo(
            $this->data,
            labels('notifications', 'Notifications') . ' | ' . labels('provider_panel', 'Provider Panel'),
            'notifications'
        );

        return view('backend/partner/template', $this->data);
    }

    /**
     * Latest 5 notifications for the header dropdown.
     *
     * Response shape is intentionally simple so the JS can render quickly.
     */
    public function recent()
    {
        try {
            if (!($this->isLoggedIn && !$this->userIsAdmin)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'data' => [],
                ]);
            }

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error' => true,
                    'message' => labels('access_denied', 'Access denied'),
                    'data' => [],
                ]);
            }

            // IMPORTANT:
            // We fetch notifications "for provider audience" so partners do not
            // see customer-only broadcasts.
            //
            // Performance:
            // - Use the lite SELECT to avoid returning unused columns.
            // - Render system-generated templates on the server.
            // - Also return unread_count so the bell badge can be updated without extra calls.
            $rows = $this->notificationModel->fetchForAudienceDropdownLite($this->userId, 'provider', 5, 0);
            $unreadCount = $this->notificationModel->countUnreadForAudience($this->userId, 'provider');

            $templateService = new NotificationTemplateService();
            $out = [];
            foreach ($rows as $n) {
                if (($n['event_type'] ?? '') === 'system_generated') {
                    $n = $this->renderSystemGenerated($n, $this->userId, $templateService);
                }

                $ts = $n['date_sent'] ?? $n['created_at'] ?? null;
                $out[] = [
                    'id' => $n['id'] ?? null,
                    'title' => $n['title'] ?? '',
                    'message' => $n['message'] ?? '',
                    'url' => $n['url'] ?? '',
                    // These two fields are needed for click redirect rules when notifications
                    // are created by admin (event_type = sent_by_admin).
                    'event_type' => $n['event_type'] ?? '',
                    // For system_generated, redirect rules use template key (event_key), not "system_generated".
                    'event_key' => $n['event_key'] ?? $n['type'] ?? '',
                    'notification_type' => $n['notification_type'] ?? '',
                    'is_readed' => $n['is_readed'] ?? 0,
                    /**
                     * Redirect context (provider panel)
                     * -------------------------------
                     * Why:
                     * - The provider header notification dropdown must redirect to the correct
                     *   destination page when clicked (order/subscription/categories).
                     *
                     * Security:
                     * - We DO NOT expose the full raw `context_data` blob to the browser.
                     * - We only send the minimal identifiers required for redirects.
                     *
                     * Frontend contract:
                     * - JS checks for `order_id` OR `booking_id` and redirects to the order view.
                     * - If `subscription_id` exists, redirect to subscription page.
                     * - If `category_id` exists, redirect to categories list.
                     */
                    'context_data' => $this->extractRedirectContext($n['context_data'] ?? null),
                    // For UI: same "duration" style used in NotificationApiController.php
                    'duration' => $this->duration($ts),
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('notifications_fetched_successfully', 'Notifications fetched successfully'),
                'data' => $out,
                'unread_count' => (int) $unreadCount,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Notifications.php - recent()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Mark all provider-visible notifications as read.
     *
     * Used by the bell dropdown "Mark all as read" action.
     */
    public function mark_all_read()
    {
        try {
            if (!($this->isLoggedIn && !$this->userIsAdmin)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error' => true,
                    'message' => labels('access_denied', 'Access denied'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $ok = $this->notificationModel->markAllAsReadForAudience($this->userId, 'provider');
            $unreadCount = $this->notificationModel->countUnreadForAudience($this->userId, 'provider');

            return $this->response->setJSON([
                'error' => $ok ? false : true,
                'message' => $ok
                    ? labels('notifications_marked_as_read', 'Notifications marked as read')
                    : labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'unread_count' => (int) $unreadCount,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Notifications.php - mark_all_read()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Mark a single provider-visible notification as read.
     *
     * Used by:
     * - Partner header notification dropdown item click (before redirect).
     */
    public function mark_read()
    {
        try {
            if (!($this->isLoggedIn && !$this->userIsAdmin)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error' => true,
                    'message' => labels('access_denied', 'Access denied'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $id = (int) ($this->request->getPost('notification_id') ?? 0);
            if ($id <= 0) {
                return $this->response->setStatusCode(400)->setJSON([
                    'error' => true,
                    'message' => labels('invalid_request', 'Invalid request'),
                    'unread_count' => (int) $this->notificationModel->countUnreadForAudience($this->userId, 'provider'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            // Safety: only allow marking notifications that are visible to this provider.
            if (!$this->notificationModel->isVisibleToAudience($this->userId, 'provider', $id)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => true,
                    'message' => labels('not_found', 'Not found'),
                    'unread_count' => (int) $this->notificationModel->countUnreadForAudience($this->userId, 'provider'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            // Mark by id (more reliable than reusing the complex audience WHERE inside UPDATE).
            $ok = $this->notificationModel->markAsReadById($id);
            $unreadCount = $this->notificationModel->countUnreadForAudience($this->userId, 'provider');

            return $this->response->setJSON([
                'error' => $ok ? false : true,
                'message' => $ok
                    ? labels('notifications_marked_as_read', 'Notifications marked as read')
                    : labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'unread_count' => (int) $unreadCount,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Notifications.php - mark_read()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Validate that a notification redirect target still exists before redirecting.
     *
     * Used by notification_redirects.js so we don't redirect to order pages
     * when the related user has been deleted from the users table.
     *
     * Expects POST: target_type = 'order' | 'provider', target_id = <id>
     * Returns: { exists: true|false, ... }
     */
    public function validate_redirect_target()
    {
        try {
            if (!($this->isLoggedIn && !$this->userIsAdmin)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'exists' => false,
                    'message' => labels('unauthorized', 'Unauthorized'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error' => true,
                    'exists' => false,
                    'message' => labels('access_denied', 'Access denied'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $targetType = strtolower(trim((string) ($this->request->getPost('target_type') ?? $this->request->getGet('target_type') ?? '')));
            $targetId = (int) ($this->request->getPost('target_id') ?? $this->request->getGet('target_id') ?? 0);

            if ($targetId <= 0 || !in_array($targetType, ['order', 'provider'], true)) {
                return $this->response->setJSON([
                    'error' => true,
                    'exists' => false,
                    'message' => labels('invalid_request', 'Invalid request'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $exists = false;
            // Use Users_model so soft-deleted users are not considered "existing".
            $usersModel = new Users_model();

            if ($targetType === 'provider') {
                // Provider ID is a user id; user must exist and not be soft-deleted.
                $exists = $usersModel->find($targetId) !== null;
            } elseif ($targetType === 'order') {
                // Order must exist and both customer and provider (users) must still exist and not be soft-deleted.
                $order = fetch_details('orders', ['id' => $targetId]);
                if (!empty($order) && !empty($order[0])) {
                    $userId = (int) ($order[0]['user_id'] ?? 0);
                    $partnerId = (int) ($order[0]['partner_id'] ?? 0);
                    $exists = ($userId > 0 && $partnerId > 0
                        && $usersModel->find($userId) !== null
                        && $usersModel->find($partnerId) !== null);
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'exists' => $exists,
                'message' => $exists ? labels('ok', 'OK') : labels('user_no_longer_exists', 'User no longer exists'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Notifications.php - validate_redirect_target()');
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'exists' => false,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Paginated list endpoint for the "View all notifications" table.
     *
     * Bootstrap-table expects:
     * - { total: <int>, rows: <array> }
     */
    public function list()
    {
        try {
            if (!($this->isLoggedIn && !$this->userIsAdmin)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'total' => 0,
                    'rows' => [],
                ]);
            }

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'total' => 0,
                    'rows' => [],
                ]);
            }

            $limit = max((int) ($this->request->getGet('limit') ?? 10), 1);
            $offset = max((int) ($this->request->getGet('offset') ?? 0), 0);

            // Keep ordering consistent and predictable: newest first.
            $total = $this->notificationModel->countForAudience($this->userId, 'provider');
            $rows = $this->notificationModel->fetchForAudience($this->userId, 'provider', $limit, $offset);

            // Keep only the fields the web UI needs (reduces payload + avoids leaking context_data).
            $safeRows = [];
            foreach ($rows as $n) {
                $safeRows[] = [
                    'id' => $n['id'] ?? null,
                    'title' => $n['title'] ?? '',
                    'message' => $n['message'] ?? '',
                    'type' => $n['type'] ?? '',
                    'notification_type' => $n['notification_type'] ?? '',
                    'order_id' => $n['order_id'] ?? null,
                    'url' => $n['url'] ?? '',
                    'is_readed' => $n['is_readed'] ?? 0,
                    'date_sent' => $n['date_sent'] ?? '',
                    'created_at' => $n['created_at'] ?? '',
                ];
            }

            return $this->response->setJSON([
                'total' => (int) $total,
                'rows' => $safeRows,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Notifications.php - list()');
            return $this->response->setStatusCode(500)->setJSON([
                'total' => 0,
                'rows' => [],
            ]);
        }
    }

    /**
     * Ultra-lean endpoint for the partner notifications bootstrap-table.
     *
     * Requirements:
     * - server-side pagination (limit/offset)
     * - blazing fast, minimal redundancy/payload
     * - includes "time ago" (human diff) column
     *
     * Bootstrap-table expects: { total: <int>, rows: <array> }
     */
    public function table()
    {
        try {
            if (!($this->isLoggedIn && !$this->userIsAdmin)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'total' => 0,
                    'rows' => [],
                ]);
            }

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'total' => 0,
                    'rows' => [],
                ]);
            }

            // Only accept sane pagination values.
            $limit = max((int) ($this->request->getGet('limit') ?? 10), 1);
            $offset = max((int) ($this->request->getGet('offset') ?? 0), 0);

            // Search text (bootstrap-table uses `search`).
            $search = (string) ($this->request->getGet('search') ?? $this->request->getGet('searchText') ?? '');
            $search = trim($search);

            // If searching, also search in template title/body (translated + base),
            // then map matches to event keys so we can match system_generated rows by `type`.
            $templateEventKeys = [];
            if ($search !== '') {
                $templateEventKeys = $this->getTemplateEventKeysForSearch($search);
            }

            // Minimal queries:
            // - 1x count (for pagination)
            // - 1x select with only required columns (title/message/time)
            if ($search !== '') {
                $total = $this->notificationModel->countForAudienceTableSearch($this->userId, 'provider', $search, $templateEventKeys);
                $rows = $this->notificationModel->fetchForAudienceTableLiteSearch($this->userId, 'provider', $limit, $offset, $search, $templateEventKeys);
            } else {
                $total = $this->notificationModel->countForAudience($this->userId, 'provider');
                $rows = $this->notificationModel->fetchForAudienceTableLite($this->userId, 'provider', $limit, $offset);
            }

            $templateService = new NotificationTemplateService();
            $out = [];
            foreach ($rows as $n) {
                // Prefer date_sent when present; otherwise fall back to created_at.
                if (($n['event_type'] ?? '') === 'system_generated') {
                    $n = $this->renderSystemGenerated($n, $this->userId, $templateService);
                }

                $ts = $n['date_sent'] ?? $n['created_at'] ?? null;
                $out[] = [
                    // Keep `id` for stable UI toggles (read more/less) and for row click redirect.
                    'id' => $n['id'] ?? null,
                    'title' => $n['title'] ?? '',
                    'message' => $n['message'] ?? '',
                    // Same "duration" style used by NotificationApiController.php (human diff)
                    'time_ago' => $this->duration($ts),
                    // Redirect metadata: event_type, event_key (template key), context_data, url (for sent_by_admin + url).
                    'event_type' => $n['event_type'] ?? '',
                    'event_key' => $n['event_key'] ?? $n['type'] ?? '',
                    'notification_type' => $n['notification_type'] ?? '',
                    'url' => $n['url'] ?? '',
                    'context_data' => $this->extractRedirectContext($n['context_data'] ?? null),
                ];
            }

            return $this->response->setJSON([
                'total' => (int) $total,
                'rows' => $out,
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Notifications.php - table()');
            return $this->response->setStatusCode(500)->setJSON([
                'total' => 0,
                'rows' => [],
            ]);
        }
    }

    /**
     * Search helper for template-driven notifications.
     *
     * We search template title/body (base + translated) and return matching event_key list.
     * This allows the notifications query to match system_generated notifications by `type`.
     *
     * NOTE:
     * Templates are few compared to notifications, so doing this extra query is cheap.
     */
    private function getTemplateEventKeysForSearch(string $search): array
    {
        $q = trim($search);
        if ($q === '') {
            return [];
        }

        helper('function');
        $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';
        $defaultLang = function_exists('get_default_language') ? get_default_language() : 'en';

        $db = \Config\Database::connect();
        $builder = $db->table('notification_templates nt');

        // Left join translations for current/default language.
        // We put the language restriction in the join condition to keep it a true LEFT JOIN.
        $join = 't.template_id = nt.id AND t.language_code IN (' . $db->escape($currentLang) . ', ' . $db->escape($defaultLang) . ')';
        $builder->join('translated_notification_templates t', $join, 'left', false);

        $builder->select('DISTINCT nt.event_key', false);
        $builder->groupStart()
            ->like('nt.title', $q)
            ->orLike('nt.body', $q)
            ->orLike('t.title', $q)
            ->orLike('t.body', $q)
            ->groupEnd();

        // Safety: templates are small; still cap to keep things predictable.
        $rows = $builder->limit(200)->get()->getResultArray();

        $keys = [];
        foreach ($rows as $r) {
            if (!empty($r['event_key'])) {
                $keys[] = $r['event_key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Render system-generated notification title/message using templates.
     *
     * This mirrors logic already implemented in:
     * - app/Controllers/api/NotificationApiController.php
     */
    private function renderSystemGenerated(array $n, $userId, NotificationTemplateService $templateService): array
    {
        $eventKey = $n['type'] ?? '';
        $context = json_decode($n['context_data'] ?? '{}', true) ?: [];
        $context['user_id'] = $context['user_id'] ?? $userId;

        if (!$eventKey) {
            return $n;
        }

        $tpl = $templateService->getFcmTemplate($eventKey);
        if (!$tpl) {
            $n['event_key'] = $eventKey;
            return $n;
        }

        $vars = $templateService->extractVariablesFromContext($eventKey, $context);

        $n['title'] = $templateService->replaceVariables($tpl['title'] ?? '', $vars);
        $n['message'] = $templateService->replaceVariables($tpl['body'] ?? '', $vars);
        $n['event_key'] = $eventKey;

        return $n;
    }

    /**
     * Human time diff (matches Flutter app logic).
     *
     * DB returns all timestamps normalized to UTC via CONVERT_TZ.
     * No explicit timezone conversion needed—use app timezone (which is UTC).
     */
    private function duration(?string $date): string
    {
        if (!$date)
            return '';

        try {
            $utcDate = new Time($date);
            $now = Time::now();

            $diff = $now->getTimestamp() - $utcDate->getTimestamp();
            $isPast = $diff >= 0;
            $elapsed = abs($diff);

            $seconds = $elapsed;
            $minutes = (int) floor($elapsed / 60);
            $hours = (int) floor($elapsed / 3600);
            $days = (int) floor($elapsed / 86400);

            if ($seconds < 45) {
                $msg = labels('less_than_a_minute', 'less than a minute');
            } elseif ($seconds < 90) {
                $msg = labels('about_a_minute', 'about a minute');
            } elseif ($minutes < 45) {
                $msg = $this->formatPlural($minutes, labels('minute', 'minute'));
            } elseif ($minutes < 90) {
                $msg = labels('about_an_hour', 'about an hour');
            } elseif ($hours < 24) {
                $msg = $this->formatPlural($hours, labels('hour', 'hour'));
            } elseif ($hours < 48) {
                $msg = labels('a_day', 'a day');
            } elseif ($days < 7) {
                $msg = $this->formatPlural($days, labels('day', 'day'));
            } elseif ($days < 14) {
                $msg = labels('about_a_week', 'about a week');
            } elseif ($days < 30) {
                $weeks = (int) floor($days / 7);
                $msg = $this->formatPlural($weeks, labels('week', 'week'));
            } elseif ($days < 60) {
                $msg = labels('about_a_month', 'about a month');
            } elseif ($days < 365) {
                $months = (int) floor($days / 30);
                $msg = $this->formatPlural($months, labels('month', 'month'));
            } elseif ($days < 730) {
                $msg = labels('about_a_year', 'about a year');
            } else {
                $years = (int) floor($days / 365);
                $msg = $this->formatPlural($years, labels('year', 'year'));
            }

            return $isPast ? $msg . ' ' . labels('ago', 'ago') : labels('in', 'in') . ' ' . $msg;
        } catch (\Exception $e) {
            return '';
        }
    }

    private function formatPlural(int $value, string $unit): string
    {
        return $value === 1 ? "1 $unit" : "$value {$unit}s";
    }


    /**
     * Extract only the redirect-relevant keys from `notifications.context_data`.
     *
     * IMPORTANT:
     * - Keep this small and explicit.
     * - Do NOT leak additional context into the browser unless needed.
     */
    private function extractRedirectContext(?string $contextDataJson): array
    {
        $ctx = json_decode($contextDataJson ?? '', true);
        if (!is_array($ctx)) {
            $ctx = [];
        }

        // Keep keys aligned with `public/backend/assets/js/notification_redirects.js`
        $allowedKeys = ['order_id', 'booking_id', 'subscription_id', 'provider_id', 'service_id', 'category_id'];
        $out = [];

        foreach ($allowedKeys as $k) {
            if (array_key_exists($k, $ctx) && $ctx[$k] !== null && $ctx[$k] !== '') {
                $out[$k] = $ctx[$k];
            }
        }

        return $out;
    }
}
