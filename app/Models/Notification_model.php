<?php

namespace App\Models;

use CodeIgniter\Model;

class Notification_model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['title', 'message', 'type', 'type_id', 'image', 'order_id', 'user_id', 'is_readed', 'notification_type', 'date_sent', 'target', 'url', 'context_data', 'event_type'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Base query constraints for app notifications.
     *
     * Why this exists:
     * - We want **one** canonical definition of "what notifications a user can see".
     * - Both Customer app API and Provider app API should reuse the same logic.
     *
     * What it includes:
     * - system_generated notifications for that user_id (supports legacy string/int storage)
     * - broadcasts to "all_users"
     * - broadcasts to a specific app audience (customer/provider)
     * - specific_user notifications where user_id is stored as JSON array
     * - legacy direct user_id match (non JSON)
     *
     * @param int|string $userId
     * @param string $audienceTarget Allowed values: 'customer' or 'provider'
     */
    protected function baseConditionsForAudience($userId, string $audienceTarget)
    {
        $idStr = (string)$userId;

        // Start outer group: ALL audience conditions must be inside this single group
        // Structure: (condition1 OR condition2 OR condition3 OR condition4)
        $builder = $this->db->table($this->table)
            ->groupStart()

            // Audience condition 1: system_generated + matching user_id
            ->groupStart()
            ->where('event_type', 'system_generated')
            ->groupStart()
            ->where('user_id', $idStr)
            ->orWhere('user_id', $userId)
            ->groupEnd()
            ->groupEnd()

            // Audience condition 2: target IN ('all_users', $audienceTarget)
            ->orGroupStart()
            ->whereIn('target', ['all_users', $audienceTarget])
            ->groupEnd()

            // Audience condition 3: target = 'specific_user' + JSON_CONTAINS(user_id, ...)
            ->orGroupStart()
            ->where('target', 'specific_user')
            ->groupStart()
            ->where("JSON_CONTAINS(user_id, '\"{$idStr}\"')", null, false)
            ->orWhere("JSON_CONTAINS(user_id, '{$idStr}')", null, false)
            ->groupEnd()
            ->groupEnd()

            // Audience condition 4: legacy direct user_id match
            ->orGroupStart()
            ->where('user_id', $idStr)
            ->orWhere('user_id', $userId)
            ->groupEnd()

            // Close outer group: all audience conditions are now grouped together
            ->groupEnd();

        // Apply registration cutoff AFTER the audience group (ANDed, not ORed)
        // This ensures users only see notifications created after their registration
        $builder->where(
            "UNIX_TIMESTAMP(
                COALESCE(date_sent, FROM_UNIXTIME(created_at))
            ) >= (
                SELECT UNIX_TIMESTAMP(created_at)
                FROM users
                WHERE id = {$this->db->escape($userId)}
            )",
            null,
            false
        );


        // log_message('error', $builder->getCompiledSelect());
        return $builder;
    }


    /**
     * Base query constraints for "web panel inbox" notifications by user_id column.
     *
     * Why this exists:
     * - In web panels (especially admin), we must NOT show notifications just because they are
     *   broadcasts by `target` (e.g. `all_users`).
     * - We must show only notifications that are explicitly stored for the logged-in user
     *   in `notifications.user_id`.
     *
     * Important DB reality:
     * - For `system_generated`, we store one notification per user and `user_id` is a scalar string/int.
     * - For admin-sent notifications (`event_type = sent_by_admin`) when sending to multiple users,
     *   `user_id` can be stored as a JSON array (e.g. ["12","99"] or [12,99]).
     *
     * Safety:
     * - We guard JSON_CONTAINS with JSON_VALID to avoid SQL errors when `user_id` is not JSON.
     */
    protected function baseConditionsForUserIdColumn($userId)
    {
        $idStr = (string)$userId;

        $builder = $this->db->table($this->table)
            ->groupStart()

            // Scalar match (legacy + system_generated rows)
            ->groupStart()
            ->where('user_id', $idStr)
            ->orWhere('user_id', $userId)
            ->groupEnd()

            // JSON array match (admin "specific_user" and similar multi-recipient rows)
            ->orGroupStart()
            ->where('JSON_VALID(user_id) = 1', null, false)
            ->groupStart()
            ->where("JSON_CONTAINS(user_id, '\"{$idStr}\"')", null, false)
            ->orWhere("JSON_CONTAINS(user_id, '{$idStr}')", null, false)
            ->groupEnd()
            ->groupEnd()

            ->groupEnd();

        /**
         * ✅ Registration cutoff — DATETIME SAFE
         * Compare DATETIME ↔ DATETIME inside SQL
         */
        $builder->where(
            "UNIX_TIMESTAMP(
                COALESCE(date_sent, FROM_UNIXTIME(created_at))
            ) >= (
                SELECT UNIX_TIMESTAMP(created_at)
                FROM users
                WHERE id = {$this->db->escape($userId)}
            )",
            null,
            false
        );

        // log_message('error', $builder->getCompiledSelect());
        return $builder;
    }


    /**
     * Translate notification type to user-friendly text
     * 
     * Maps notification type values (general, provider, category, url, booking)
     * to their translated labels using the labels() function.
     * 
     * @param string $type The notification type value from database
     * @return string Translated notification type text
     */
    private function translateNotificationType($type)
    {
        // Load helper function if not already loaded
        helper('function');

        // Map notification types to their translation keys
        $typeMap = [
            'general' => 'notification_type_general',
            'provider' => 'notification_type_provider',
            'category' => 'notification_type_category',
            'url' => 'notification_type_url',
            'booking' => 'notification_type_booking',
            'order' => 'notification_type_booking', // order is converted to booking
        ];

        // Get translation key for this type
        $translationKey = $typeMap[strtolower($type)] ?? null;

        // If we have a translation key, use labels() function to get translated text
        if ($translationKey && function_exists('labels')) {
            // Use labels() function with fallback to capitalized type name
            return labels($translationKey, ucfirst($type));
        }

        // Fallback to capitalized type name if translation not found
        return ucfirst($type);
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $whereIn = [])
    {
        $db = \Config\Database::connect();
        $builder = $db->table('notifications n');

        // Apply search filter
        if (!empty($search)) {
            $builder->groupStart()
                ->orLike('n.id', $search)
                ->orLike('n.title', $search)
                ->orLike('n.message', $search)
                ->orLike('n.type', $search)
                ->orLike('n.type_id', $search)
                ->groupEnd();
        }

        // Apply where conditions
        if (!empty($where)) {
            $builder->where($where);
        }

        // Apply whereIn conditions for type
        if (!empty($whereIn['type'])) {
            $builder->WhereIn('type', $whereIn['type']); // Use `orWhereIn` instead of `whereIn`
        }

        // // Modify JSON_CONTAINS condition to use OR instead of AND
        // if (!empty($whereIn['user_id'])) {
        //     $userId = $whereIn['user_id'][0]; // Assuming single user_id
        //     $builder->orGroupStart()
        //         ->orWhere("JSON_CONTAINS(user_id, '\"$userId\"')")
        //         ->orWhere("JSON_CONTAINS(user_id, '\"-\"')")
        //         ->groupEnd();
        // }


        // Modify JSON_CONTAINS condition to use OR instead of AND
        if (!empty($whereIn['user_id'])) {
            $userId = $whereIn['user_id'][0]; // Assuming single user_id
            $builder->GroupStart()
                ->orWhere("JSON_CONTAINS(user_id, '\"$userId\"')")
                ->orWhere("JSON_CONTAINS(user_id, '\"-\"')")
                ->groupEnd();
        }


        // Get total count
        $total = clone $builder;
        $total = $total->countAllResults(false);

        // Fetch paginated records
        $notification_records = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $db = \Config\Database::connect();

        // Load helper function once before the loop (for translation support)
        helper('function');

        // Prepare response data
        $rows = [];
        foreach ($notification_records as $notification) {

            if ($notification['type'] == 'order') {
                $notification['type'] = 'booking';
            }

            // Translate notification type and notification_type fields
            // Translate type field
            $translatedType = $this->translateNotificationType($notification['type']);

            // Translate notification_type field (if it exists and is not empty)
            $translatedNotificationType = '';
            if (!empty($notification['notification_type'])) {
                $translatedNotificationType = $this->translateNotificationType($notification['notification_type']);
            }

            if ($from_app == false) {
                // Check if image exists and is not empty
                if (!empty($notification['image']) && check_exists(base_url('/public/uploads/notification/' . $notification['image']))) {
                    $image = '<a  href="' . base_url('/public/uploads/notification/' . $notification['image']) . '" data-lightbox="image-1"><img class="o-media__img images_in_card" src="' .  base_url('/public/uploads/notification/' . $notification['image']) . '" alt="' .     $notification['id'] . '"></a>';
                } else {
                    // Show placeholder bell icon when no image
                    $image = '<div class="text-center" style="font-size: 24px; color: #6c757d;"><i class="fas fa-bell"></i></div>';
                }
            } else {
                if (!empty($notification['image']) && check_exists(base_url('/public/uploads/notification/' . $notification['image']))) {
                    $image = base_url('/public/uploads/notification/' . $notification['image']);
                } else {
                    $image = '';
                }
            }



            $tempRow = [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $translatedType, // Use translated type
                'user_id' => !empty($notification['user_id']) ? json_decode($notification['user_id']) : "",
                'type_id' => $notification['type_id'],
                'image' => $image,
                'order_id' => $notification['order_id'],
                'is_readed' => $notification['is_readed'],
                'date_sent' => $notification['date_sent'],
                'notification_type' => $translatedNotificationType, // Use translated notification_type
                'url' => $notification['url'],

            ];

            if (!$from_app) {
                $tempRow['operations'] = '
                    <button class="btn btn-danger delete-notification" data-id="' . $notification['id'] . '" data-toggle="modal" data-target="#delete_modal" onclick="notification_id(this)" title="Delete the notification">
                        <i class="fa fa-trash" aria-hidden="true"></i>
                    </button>';
            }

            $rows[] = $tempRow;
        }

        // Return JSON response
        return $from_app ? ['total' => $total, 'data' => $rows] : json_encode(['total' => $total, 'rows' => $rows]);
    }

    public function getUnreadCount($userId, $target, $tab = null,)
    {
        $db = \Config\Database::connect();
        $builder = $db->table($this->table);

        // If tab is specified, return count for just that tab
        if ($tab == 'general') {
            // All users notifications that are unread
            $allUsersCount = $builder->where('target', 'all_users')
                ->where('is_readed', 0)
                ->countAllResults();

            // Provider notifications that are unread
            $providerCount = $builder->where('target', $target)
                ->where('is_readed', 0)
                ->countAllResults();

            return $allUsersCount + $providerCount;
        } else if ($tab == 'personal') {
            // Specific user notifications for this provider that are unread
            return $builder->where('target', 'specific_user')
                ->where("JSON_CONTAINS(user_id, '\"$userId\"')")
                ->orWhere("JSON_CONTAINS(user_id, '$userId')")
                ->where('is_readed', 0)
                ->countAllResults();
        }

        // Otherwise return total counts
        // All users notifications that are unread
        $allUsersCount = $builder->where('target', 'all_users')
            ->where('is_readed', 0)
            ->countAllResults();

        // Specific user notifications for this provider that are unread
        $specificUserCount = $builder->where('target', 'specific_user')
            ->where("JSON_CONTAINS(user_id, '\"$userId\"')")
            ->orWhere("JSON_CONTAINS(user_id, '$userId')")
            ->where('is_readed', 0)
            ->countAllResults();

        // Provider notifications that are unread
        $providerCount = $builder->where('target', $target)
            ->where('is_readed', 0)
            ->countAllResults();

        // If no tab specified, return total and individual counts for UI badges
        return [
            'total' => $allUsersCount + $specificUserCount + $providerCount,
            'general' => $allUsersCount + $providerCount,
            'personal' => $specificUserCount
        ];
    }

    /**
     * Shared fetch method for both customer/provider app APIs.
     *
     * @param int|string $userId
     * @param string $audienceTarget 'customer' or 'provider'
     */
    public function fetchForAudience($userId, string $audienceTarget, int $limit, int $offset): array
    {
        helper('function');

        $rows = $this->baseConditionsForAudience($userId, $audienceTarget)
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        /**
         * API safety:
         * For admin-panel notifications, the DB stores only the image filename.
         * The mobile apps expect a full URL in the `image` field.
         *
         * We ONLY build full URLs for admin notifications to avoid changing
         * the shape/meaning of other notification sources.
         */
        $disk = function_exists('fetch_current_file_manager') ? fetch_current_file_manager() : 'local_server';
        foreach ($rows as &$n) {
            $image = $n['image'] ?? '';
            if (empty($image)) {
                continue;
            }

            // If already a full URL, keep as-is.
            if (preg_match('#^https?://#i', $image)) {
                continue;
            }

            // Only apply to admin notifications created from admin panel.
            if (($n['event_type'] ?? '') !== 'sent_by_admin') {
                continue;
            }

            if ($disk === 'aws_s3' && function_exists('fetch_cloud_front_url')) {
                $n['image'] = fetch_cloud_front_url('notification', $image);
            } else {
                // Local server path: `public/uploads/notification/<filename>`
                $n['image'] = base_url('public/uploads/notification/' . $image);
            }
        }

        return $rows;
    }

    /**
     * Lightweight fetch for web tables (partner/admin panels).
     *
     * Why this exists:
     * - Some UI surfaces only need a tiny subset of columns (title/message/time).
     * - Using a reduced SELECT keeps payload small and improves query speed.
     *
     * Notes:
     * - We intentionally do NOT build image URLs here (unneeded for this table).
     * - We intentionally do NOT return context_data (avoid leaking sensitive payload).
     */
    public function fetchForAudienceLite($userId, string $audienceTarget, int $limit, int $offset): array
    {
        return $this->baseConditionsForAudience($userId, $audienceTarget)
            ->select('id, title, message, date_sent, created_at')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Lightweight fetch for the partner bell dropdown.
     *
     * Why:
     * - The dropdown needs a small payload (title/message/url/time + read flag).
     * - Avoid returning unused columns (saves bandwidth + CPU).
     */
    public function fetchForAudienceDropdownLite($userId, string $audienceTarget, int $limit, int $offset = 0): array
    {
        return  $this->baseConditionsForAudience($userId, $audienceTarget)
            // We include type/event_type/context_data so the controller can render
            // template-based notifications (system_generated) before returning to UI.
            // We also include `notification_type` so the UI can apply redirect logic for
            // admin-sent notifications (event_type = sent_by_admin).
            ->select('id, title, message, url, is_readed, date_sent, created_at, type, notification_type, event_type, context_data')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Web panel dropdown: fetch latest notifications for a specific user_id (strict).
     *
     * Admin requirement:
     * - Do NOT use `target`-based visibility here.
     * - Only show rows that explicitly belong to the logged-in user via `user_id`.
     */
    public function fetchForUserDropdownLite($userId, int $limit, int $offset = 0): array
    {
        return $this->baseConditionsForUserIdColumn($userId)
            // Include fields needed for template rendering in controller (system_generated).
            // Include `notification_type` for consistent redirect behavior across panels.
            ->select('id, title, message, url, is_readed, date_sent, created_at, type, notification_type, event_type, context_data')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Lightweight fetch for the partner notifications "View all" bootstrap-table.
     *
     * We include only what the server needs to render:
     * - title/message for normal notifications
     * - type/event_type/context_data for template-based notifications
     * - timestamps for the human "time ago" column
     */
    public function fetchForAudienceTableLite($userId, string $audienceTarget, int $limit, int $offset = 0): array
    {
        return $this->baseConditionsForAudience($userId, $audienceTarget)
            ->select('id, title, message, date_sent, created_at, type, event_type, context_data, notification_type, url')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Web panel table: fetch notifications for a specific user_id (strict, minimal payload).
     */
    public function fetchForUserTableLite($userId, int $limit, int $offset = 0): array
    {
        return $this->baseConditionsForUserIdColumn($userId)
            ->select('id, title, message, date_sent, created_at, type, event_type, context_data, notification_type, url')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Apply search conditions for partner notifications (title/message + template-backed).
     *
     * Search rules:
     * - Always search on stored `title` and `message`
     * - For template-driven notifications (event_type = system_generated):
     *   - allow matching by template event keys (type IN <event_keys>)
     *   - allow matching by context_data (best-effort)
     *
     * This keeps queries fast while still "handling templates" reasonably.
     */
    private function applyPartnerSearch($builder, string $search, array $templateEventKeys = [])
    {
        $q = trim($search);
        if ($q === '') {
            return $builder;
        }

        // Basic LIKE search (title/message)
        $builder->groupStart()
            ->like('title', $q)
            ->orLike('message', $q);

        // Template-driven notifications: match by event key or context_data.
        $builder->orGroupStart()
            ->where('event_type', 'system_generated')
            ->groupStart();

        if (!empty($templateEventKeys)) {
            $builder->whereIn('type', $templateEventKeys);
        }

        // Best-effort match for dynamic variables.
        $builder->orLike('context_data', $q);

        $builder->groupEnd()
            ->groupEnd()
            ->groupEnd();

        return $builder;
    }

    /**
     * Search-aware count for the partner notifications table.
     */
    public function countForAudienceTableSearch($userId, string $audienceTarget, string $search, array $templateEventKeys = []): int
    {
        $builder = $this->baseConditionsForAudience($userId, $audienceTarget);
        $this->applyPartnerSearch($builder, $search, $templateEventKeys);
        return (int)$builder->countAllResults();
    }

    /**
     * Web panel table: search-aware count for a specific user_id (strict).
     */
    public function countForUserTableSearch($userId, string $search, array $templateEventKeys = []): int
    {
        $builder = $this->baseConditionsForUserIdColumn($userId);
        $this->applyPartnerSearch($builder, $search, $templateEventKeys);
        return (int)$builder->countAllResults();
    }

    /**
     * Search-aware fetch for the partner notifications table.
     */
    public function fetchForAudienceTableLiteSearch($userId, string $audienceTarget, int $limit, int $offset, string $search, array $templateEventKeys = []): array
    {
        $builder = $this->baseConditionsForAudience($userId, $audienceTarget)
            ->select('id, title, message, date_sent, created_at, type, event_type, context_data, notification_type, url')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset);

        $this->applyPartnerSearch($builder, $search, $templateEventKeys);

        return $builder->get()->getResultArray();
    }

    /**
     * Web panel table: search-aware fetch for a specific user_id (strict).
     */
    public function fetchForUserTableLiteSearch($userId, int $limit, int $offset, string $search, array $templateEventKeys = []): array
    {
        $builder = $this->baseConditionsForUserIdColumn($userId)
            ->select('id, title, message, date_sent, created_at, type, event_type, context_data, notification_type, url')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset);

        $this->applyPartnerSearch($builder, $search, $templateEventKeys);

        return $builder->get()->getResultArray();
    }

    /**
     * Count unread notifications for an audience.
     *
     * NOTE:
     * We deliberately keep this in the model so all callers share the exact same
     * "audience visibility" rules (broadcasts + specific_user + system_generated).
     */
    public function countUnreadForAudience($userId, string $audienceTarget): int
    {
        return (int)$this->baseConditionsForAudience($userId, $audienceTarget)
            ->where('is_readed', 0)
            ->countAllResults();
    }

    /**
     * Count unread notifications for a specific user_id (strict).
     */
    public function countUnreadForUser($userId): int
    {
        return (int)$this->baseConditionsForUserIdColumn($userId)
            ->where('is_readed', 0)
            ->countAllResults();
    }

    /**
     * Mark all notifications as read for a given audience.
     *
     * IMPORTANT (current schema limitation):
     * - `notifications.is_readed` is stored on the notification record itself.
     * - For broadcast notifications this means "read" becomes global.
     * - This is consistent with existing usage in the app APIs (markAsRead()).
     */
    public function markAllAsReadForAudience($userId, string $audienceTarget): bool
    {
        return (bool)$this->baseConditionsForAudience($userId, $audienceTarget)
            ->set(['is_readed' => 1])
            ->update();
    }

    /**
     * Mark a single notification as read for a given audience.
     *
     * IMPORTANT (schema limitation):
     * - `is_readed` is stored on the notification row itself.
     * - If a notification row is shared (broadcast / multi-recipient JSON user_id),
     *   marking it read here will affect all recipients.
     * - This is consistent with the existing "mark all read" behaviour in this project.
     */
    public function markOneAsReadForAudience($userId, string $audienceTarget, int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        return (bool)$this->baseConditionsForAudience($userId, $audienceTarget)
            ->where('id', $notificationId)
            ->set(['is_readed' => 1])
            ->update();
    }

    /**
     * Check if a notification is visible for a given audience.
     *
     * Why:
     * - We want to safely allow "mark one as read" by id, but only when the user
     *   is allowed to see that notification in the provider panel.
     *
     * Note:
     * - This uses the SAME base conditions as dropdown/table fetch for consistency.
     */
    public function isVisibleToAudience($userId, string $audienceTarget, int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        return (int)$this->baseConditionsForAudience($userId, $audienceTarget)
            ->where('id', $notificationId)
            ->countAllResults() > 0;
    }

    /**
     * Check if a notification is visible to a specific user via `notifications.user_id` column (strict).
     *
     * Used by admin web panel mark-read endpoint to prevent tampering:
     * - Only notifications that "belong" to the logged-in admin should be markable.
     * - Supports scalar user_id values and JSON array user_id values (admin-sent notifications).
     */
    public function isVisibleToUser($userId, int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        return (int)$this->baseConditionsForUserIdColumn($userId)
            ->where('id', $notificationId)
            ->countAllResults() > 0;
    }

    /**
     * Mark a notification as read by id (no visibility rules).
     *
     * IMPORTANT:
     * - Only call this AFTER you have verified visibility (e.g. via isVisibleToAudience()).
     */
    public function markAsReadById(int $notificationId): bool
    {
        if ($notificationId <= 0) {
            return false;
        }

        // Use a *fresh* query builder instance.
        // Why:
        // - CI Model builder state can be influenced by previous builder usage in the same request.
        // - Using `$this->db->table($this->table)` guarantees no residual WHERE/group state.
        $db = \Config\Database::connect();
        $ok = (bool)$db->table($this->table)
            ->where('id', $notificationId)
            ->update(['is_readed' => 1]);
        return $ok;
    }

    /**
     * Mark all notifications as read for a specific user_id (strict).
     *
     * NOTE (schema limitation):
     * - If a single notification row is shared for multiple recipients (JSON user_id array),
     *   marking it read will mark it read for all recipients.
     * - This is existing behaviour across the project because `is_readed` lives on the row.
     */
    public function markAllAsReadForUser($userId): bool
    {
        return (bool)$this->baseConditionsForUserIdColumn($userId)
            ->set(['is_readed' => 1])
            ->update();
    }

    /**
     * Shared count method for both customer/provider app APIs.
     *
     * @param int|string $userId
     * @param string $audienceTarget 'customer' or 'provider'
     */
    public function countForAudience($userId, string $audienceTarget): int
    {
        return (int)$this->baseConditionsForAudience($userId, $audienceTarget)->countAllResults();
    }

    /**
     * Count total notifications for a specific user_id (strict).
     */
    public function countForUser($userId): int
    {
        return (int)$this->baseConditionsForUserIdColumn($userId)->countAllResults();
    }

    public function markAsRead(array $ids)
    {
        if (!empty($ids)) {
            $this->whereIn('id', $ids)->set(['is_readed' => 1])->update();
        }
    }
}
