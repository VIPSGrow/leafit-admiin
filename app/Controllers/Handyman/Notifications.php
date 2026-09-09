<?php

namespace App\Controllers\Handyman;

use App\Models\Notification_model;
use App\Services\NotificationTemplateService;
use CodeIgniter\I18n\Time;

/**
 * Handyman Notifications (web panel).
 *
 * Mirrors app/Controllers/Partner/Notifications.php:
 * - `index()`  : renders the "View all notifications" page for handymen.
 * - `recent()` : returns the latest 5 notifications for the header dropdown.
 * - `table()`  : returns a paginated list for the table on the "View all" page.
 *
 * Handyman notifications are stored with `target` = 'handyman', a value not used
 * by any admin broadcast, so a handyman only ever sees: all_users broadcasts +
 * their own system_generated rows (booking_assigned, booking_status changes, etc.).
 */
class Notifications extends Handyman
{
    private const AUDIENCE = 'handyman';

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
        $this->data['title'] = labels('notifications', 'Notifications');
        $this->data['main_page'] = 'notifications';
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('notifications', 'Notifications')],
        ];

        return view('backend/handyman/pages/notifications', $this->data);
    }

    /**
     * Latest 5 notifications for the header dropdown.
     */
    public function recent()
    {
        try {
            $rows = $this->notificationModel->fetchForAudienceDropdownLite($this->userId, self::AUDIENCE, 5, 0);
            $unreadCount = $this->notificationModel->countUnreadForAudience($this->userId, self::AUDIENCE);

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
                    'event_type' => $n['event_type'] ?? '',
                    'event_key' => $n['event_key'] ?? $n['type'] ?? '',
                    'notification_type' => $n['notification_type'] ?? '',
                    'is_readed' => $n['is_readed'] ?? 0,
                    'context_data' => $this->extractRedirectContext($n['context_data'] ?? null),
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
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Notifications.php - recent()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'data' => [],
            ]);
        }
    }

    /**
     * Mark all handyman-visible notifications as read.
     */
    public function mark_all_read()
    {
        try {
            $ok = $this->notificationModel->markAllAsReadForAudience($this->userId, self::AUDIENCE);
            $unreadCount = $this->notificationModel->countUnreadForAudience($this->userId, self::AUDIENCE);

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
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Notifications.php - mark_all_read()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Mark a single handyman-visible notification as read.
     */
    public function mark_read()
    {
        try {
            $id = (int) ($this->request->getPost('notification_id') ?? 0);
            if ($id <= 0) {
                return $this->response->setStatusCode(400)->setJSON([
                    'error' => true,
                    'message' => labels('invalid_request', 'Invalid request'),
                    'unread_count' => (int) $this->notificationModel->countUnreadForAudience($this->userId, self::AUDIENCE),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            if (!$this->notificationModel->isVisibleToAudience($this->userId, self::AUDIENCE, $id)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => true,
                    'message' => labels('not_found', 'Not found'),
                    'unread_count' => (int) $this->notificationModel->countUnreadForAudience($this->userId, self::AUDIENCE),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $ok = $this->notificationModel->markAsReadById($id);
            $unreadCount = $this->notificationModel->countUnreadForAudience($this->userId, self::AUDIENCE);

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
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Notifications.php - mark_read()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    /**
     * Validate that a notification redirect target (booking) still exists before redirecting.
     * Expects POST: target_type = 'order', target_id = <id>.
     */
    public function validate_redirect_target()
    {
        try {
            $targetType = strtolower(trim((string) ($this->request->getPost('target_type') ?? $this->request->getGet('target_type') ?? '')));
            $targetId = (int) ($this->request->getPost('target_id') ?? $this->request->getGet('target_id') ?? 0);

            if ($targetId <= 0 || $targetType !== 'order') {
                return $this->response->setJSON([
                    'error' => true,
                    'exists' => false,
                    'message' => labels('invalid_request', 'Invalid request'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            $order = fetch_details('orders', ['id' => $targetId]);
            $exists = !empty($order) && !empty($order[0]);

            return $this->response->setJSON([
                'error' => false,
                'exists' => $exists,
                'message' => $exists ? labels('ok', 'OK') : labels('user_no_longer_exists', 'User no longer exists'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Notifications.php - validate_redirect_target()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
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
     * Paginated table endpoint for the "View all notifications" page.
     */
    public function table()
    {
        try {
            $limit = max((int) ($this->request->getGet('limit') ?? 10), 1);
            $offset = max((int) ($this->request->getGet('offset') ?? 0), 0);

            $search = trim((string) ($this->request->getGet('search') ?? $this->request->getGet('searchText') ?? ''));

            $templateEventKeys = [];
            if ($search !== '') {
                $templateEventKeys = $this->getTemplateEventKeysForSearch($search);
            }

            if ($search !== '') {
                $total = $this->notificationModel->countForAudienceTableSearch($this->userId, self::AUDIENCE, $search, $templateEventKeys);
                $rows = $this->notificationModel->fetchForAudienceTableLiteSearch($this->userId, self::AUDIENCE, $limit, $offset, $search, $templateEventKeys);
            } else {
                $total = $this->notificationModel->countForAudience($this->userId, self::AUDIENCE);
                $rows = $this->notificationModel->fetchForAudienceTableLite($this->userId, self::AUDIENCE, $limit, $offset);
            }

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
                    'time_ago' => $this->duration($ts),
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
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Handyman/Notifications.php - table()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'total' => 0,
                'rows' => [],
            ]);
        }
    }

    /**
     * Search helper for template-driven notifications (same approach as Partner panel).
     */
    private function getTemplateEventKeysForSearch(string $search): array
    {
        $q = trim($search);
        if ($q === '') {
            return [];
        }

        $currentLang = function_exists('get_current_language') ? get_current_language() : 'en';
        $defaultLang = function_exists('get_default_language') ? get_default_language() : 'en';

        $db = \Config\Database::connect();
        $builder = $db->table('notification_templates nt');

        $join = 't.template_id = nt.id AND t.language_code IN (' . $db->escape($currentLang) . ', ' . $db->escape($defaultLang) . ')';
        $builder->join('translated_notification_templates t', $join, 'left', false);

        $builder->select('DISTINCT nt.event_key', false);
        $builder->groupStart()
            ->like('nt.title', $q)
            ->orLike('nt.body', $q)
            ->orLike('t.title', $q)
            ->orLike('t.body', $q)
            ->groupEnd();

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
        if (!$date) {
            return '';
        }

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
     * Extract only redirect-relevant keys from `notifications.context_data`.
     */
    private function extractRedirectContext(?string $contextDataJson): array
    {
        $ctx = json_decode($contextDataJson ?? '', true);
        if (!is_array($ctx)) {
            $ctx = [];
        }

        $allowedKeys = ['order_id', 'booking_id'];
        $out = [];

        foreach ($allowedKeys as $k) {
            if (array_key_exists($k, $ctx) && $ctx[$k] !== null && $ctx[$k] !== '') {
                $out[$k] = $ctx[$k];
            }
        }

        return $out;
    }
}
