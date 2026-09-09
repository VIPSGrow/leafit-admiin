<?php

namespace App\Models;

use CodeIgniter\Model;

class HandymanDetailsModel extends Model
{
    protected $table = 'handyman_details';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $allowedFields = [
        'handyman_id',
        'partner_id',
        'address',
        'salary',
        'total_reviews',
        'average_rating',
        'is_approved',
        'is_available',
    ];

    protected $validationRules = [
        'handyman_id' => 'required|integer',
        'partner_id' => 'required|integer',
    ];

    public function getForAssignment(int $partnerId): array
    {
        $db = \Config\Database::connect();
        return $db->table('users u')
            ->select('u.id, u.username, u.image, hd.is_available')
            ->join('handyman_details hd', 'hd.handyman_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 4')
            ->where('hd.partner_id', $partnerId)
            ->where('hd.deleted_at IS NULL')
            ->where('u.active', 1)
            // ->where('hd.is_available', 1)
            ->orderBy('u.username', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getConflictsForOrder(
        int $partnerId,
        int $orderId,
        string $date,
        string $startTime,
        string $endTime,
        int $bufferBefore,
        int $bufferAfter
    ): array {
        if ($date === '' || $startTime === '' || $endTime === '') {
            return [];
        }

        $db = \Config\Database::connect();
        $escStart = $db->escape($startTime);
        $escEnd = $db->escape($endTime);
        $bufBefore = (int) $bufferBefore;
        $bufAfter = (int) $bufferAfter;

        // Overlap condition (both windows buffered):
        //   (existing.start - buf_before) < (target.end   + buf_after)
        //   (existing.end   + buf_after)  > (target.start - buf_before)
        return $db->table('booking_handymen oh')
            ->select('oh.handyman_id AS id, u.username, oh.order_id AS current_order_id')
            ->join('orders o', 'o.id = oh.order_id')
            ->join('users u', 'u.id = oh.handyman_id')
            ->join('handyman_details hd', 'hd.handyman_id = oh.handyman_id')
            ->where('hd.partner_id', $partnerId)
            ->where('hd.deleted_at IS NULL', null, false)
            ->where('u.active', 1)
            // ->where('hd.is_available', 1)
            ->where('oh.order_id !=', $orderId)
            ->where('o.date_of_service', $date)
            ->whereIn('o.status', ['awaiting', 'confirmed', 'rescheduled', 'started'])
            ->where("(TIME_TO_SEC(o.starting_time) - ({$bufBefore} * 60)) < (TIME_TO_SEC({$escEnd})   + ({$bufAfter}  * 60))", null, false)
            ->where("(TIME_TO_SEC(o.ending_time)   + ({$bufAfter}  * 60)) > (TIME_TO_SEC({$escStart}) - ({$bufBefore} * 60))", null, false)
            ->groupBy('oh.handyman_id')
            ->orderBy('u.username', 'ASC')
            ->get()->getResultArray();
    }

    public function getAssignableHandymen(
        int $partnerId,
        int $orderId,
        string $date,
        string $startTime,
        string $endTime,
        int $bufferBefore,
        int $bufferAfter
    ): array {
        $allHandymen = $this->getForAssignment($partnerId);
        if (empty($allHandymen))
            return [];

        $db = \Config\Database::connect();
        $assignedIdSet = array_flip(array_column(
            $db->table('booking_handymen')->select('handyman_id')->where('order_id', $orderId)->get()->getResultArray(),
            'handyman_id'
        ));

        $conflicts = $this->getConflictsForOrder($partnerId, $orderId, $date, $startTime, $endTime, $bufferBefore, $bufferAfter);
        $conflictMap = array_column($conflicts, 'current_order_id', 'id');

        $fileService = service('fileService');
        $assignable = [];
        foreach ($allHandymen as $h) {
            if (isset($assignedIdSet[$h['id']]))
                continue;
            $assignable[] = [
                'id' => (int) $h['id'],
                'username' => $h['username'],
                'image' => $fileService->url($h['image'], 'profile'),
                'conflicting_order_id' => isset($conflictMap[$h['id']]) ? (int) $conflictMap[$h['id']] : null,
            ];
        }
        usort($assignable, fn($a, $b) => ($a['conflicting_order_id'] !== null) <=> ($b['conflicting_order_id'] !== null));
        return $assignable;
    }

    public function getForAdminView(int $handymanId): ?array
    {
        $db = \Config\Database::connect();
        $row = $db->table('users u')
            ->select('u.id, u.username, u.phone, u.country_code, u.email, u.image, u.active, hd.is_available, hd.address, hd.salary, pd.company_name')
            ->join('handyman_details hd', 'hd.handyman_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 4')
            ->join('partner_details pd', 'pd.partner_id = hd.partner_id', 'left')
            ->where('u.id', $handymanId)
            ->where('hd.deleted_at IS NULL', null, false)
            ->get()->getRowArray();

        if (empty($row)) {
            return null;
        }

        $fileService = service('fileService');

        return [
            'id' => (int) $row['id'],
            'username' => $row['username'] ?? '',
            'phone' => trim(($row['country_code'] ?? '') . ' ' . ($row['phone'] ?? '')),
            'email' => $row['email'] ?? '',
            'image' => $fileService->url($row['image'] ?? '', 'profile'),
            'active' => (int) ($row['active'] ?? 0),
            // 'is_available' => (int) ($row['is_available'] ?? 0),
            'address' => $row['address'] ?? '',
            'provider_name' => $row['company_name'] ?? '',
        ];
    }

    /**
     * Fetch visible handyman_details custom field definitions with resolved
     * translated labels and this handyman's stored values, for read-only display.
     */
    public function getCustomFieldsForView(int $handymanId): array
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $fields = $db->table('custom_fields')
            ->select(['id', 'field_label', 'field_type', 'file_config'])
            ->where('field_group', 'handyman_details')
            ->where('visible', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($fields)) {
            return [];
        }

        $fieldIds = array_column($fields, 'id');
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        $translations = [];
        if ($db->tableExists('translated_custom_fields')) {
            $translationRows = $db->table('translated_custom_fields tcf')
                ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                ->join('languages l', 'l.id = tcf.language_id')
                ->whereIn('tcf.custom_field_id', $fieldIds)
                ->get()
                ->getResultArray();

            foreach ($translationRows as $tr) {
                $translations[(int) $tr['custom_field_id']][$tr['language_code']] = $tr['field_label'];
            }
        }

        $values = [];
        $valueRows = $db->table('handyman_custom_field_values')
            ->select(['custom_field_id', 'value'])
            ->where('handyman_id', $handymanId)
            ->where('deleted_at IS NULL', null, false)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        foreach ($valueRows as $vr) {
            $values[(int) $vr['custom_field_id']] = $vr['value'];
        }

        $fileService = service('fileService');
        $result = [];

        foreach ($fields as $field) {
            $fieldId = (int) $field['id'];
            $label = $translations[$fieldId][$currentLang]
                ?? $translations[$fieldId][$defaultLang]
                ?? $field['field_label'];
            $value = $values[$fieldId] ?? null;
            $fieldType = strtolower(trim((string) $field['field_type']));

            $result[] = [
                'id' => $fieldId,
                'label' => $label,
                'field_type' => $fieldType,
                'value' => $fieldType === 'file' && !empty($value) ? $fileService->url($value, 'custom_fields') : $value,
            ];
        }

        return $result;
    }

    public function list(int $partnerId, bool $fromApp = false, string $search = '', int $limit = 10, int $offset = 0, string $sort = 'u.id', string $order = 'DESC', ?int $statusFilter = null): array
    {
        $db = \Config\Database::connect();
        $base = $db->table('users u')
            ->join('handyman_details hd', 'hd.handyman_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 4')
            ->join('translated_handyman_details thd', 'thd.handyman_id = u.id AND thd.deleted_at IS NULL', 'left')
            ->where('hd.partner_id', $partnerId)
            ->where('hd.deleted_at IS NULL')
            ->groupBy('u.id');

        if ($statusFilter !== null) {
            $base->where('u.active', $statusFilter);
        }

        if ($search !== '') {
            $base->groupStart()
                ->like('u.username', $search)
                ->orLike('thd.username', $search)
                ->orLike('u.phone', $search)
                ->orLike('u.email', $search)
                ->groupEnd();
        }

        $total = (clone $base)->select('COUNT(u.id) AS total')->get()->getRow()->total ?? 0;

        $rows = $base
            ->select("u.id, u.username, u.phone, u.country_code, u.email, u.image, u.active, hd.is_available, hd.address, hd.salary,
                hd.total_reviews, hd.average_rating, hd.created_at,
                (SELECT COUNT(*) FROM booking_handymen bh WHERE bh.handyman_id = u.id AND bh.status IN ('booking_ended','completed')) AS completed_bookings_count", false)
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        // Batch-fetch translations for all rows (avoids N+1)
        $translationMap = [];
        if (!$fromApp && !empty($rows)) {
            $handymanIds = array_column($rows, 'id');
            $translations = $db->table('translated_handyman_details thd')
                ->select('thd.handyman_id, l.code AS language_code, thd.username')
                ->join('languages l', 'l.id = thd.language_id')
                ->whereIn('thd.handyman_id', $handymanIds)
                ->where('thd.deleted_at IS NULL')
                ->get()
                ->getResultArray();

            foreach ($translations as $t) {
                $translationMap[(int) $t['handyman_id']][$t['language_code']] = ['username' => $t['username']];
            }
        }

        // Batch-fetch custom field values for all rows (avoids N+1)
        $customFieldMap = [];
        if (!$fromApp && !empty($rows)) {
            $handymanIds = array_column($rows, 'id');
            $cfValues = $db->table('handyman_custom_field_values')
                ->select('handyman_id, custom_field_id, value')
                ->whereIn('handyman_id', $handymanIds)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();

            foreach ($cfValues as $v) {
                $customFieldMap[(int) $v['handyman_id']]['cf_' . (int) $v['custom_field_id']] = $v['value'];
            }
        }

        $fileService = service('fileService');
        $formatted = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $imageUrl = $fileService->url($row['image'], 'profile');

            if ($fromApp) {
                $formatted[] = [
                    'id' => $id,
                    'username' => $row['username'],
                    'phone' => $row['phone'],
                    'country_code' => $row['country_code'],
                    'email' => $row['email'] ?? '',
                    'image' => $imageUrl,
                    'active' => (int) $row['active'],
                    // 'is_available' => (int) $row['is_available'],
                    'address' => $row['address'] ?? '',
                    'average_rating' => $row['average_rating'] !== null ? round((float) $row['average_rating'], 2) : 0.0,
                    'total_ratings' => (int) ($row['total_reviews'] ?? 0),
                ];
                continue;
            }

            // Panel: build HTML columns
            $viewUrl = base_url('partner/handymen/view/' . $id);
            if (!empty($row['image'])) {
                $profileThumb = '<img class="o-media__img images_in_card" src="' . $imageUrl . '" alt="' . $id . '">';
            } else {
                $initial = strtoupper(substr($row['username'] ?? '', 0, 1) ?: 'H');
                $bgColor = '#' . substr(md5($id), 0, 6);
                $profileThumb = '<div class="o-media__img images_in_card fallback-initial" '
                    . 'style="width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;'
                    . 'background-color:' . $bgColor . ';color:#fff;font-weight:bold;font-size:20px;" '
                    . 'title="' . esc($row['username'] ?? '') . '">' . $initial . '</div>';
            }

            $profileCard = '<a href="' . $viewUrl . '" class="o-media o-media--middle text-decoration-none">'
                . $profileThumb
                . '<div class="o-media__body">'
                . '<div class="provider_name_table">' . esc($row['username'] ?? '') . '</div>'
                . '<div class="provider_email_table">' . esc(trim($row['country_code'] . ' ' . $row['phone'])) . '</div>'
                . (!empty($row['email']) ? '<div class="provider_email_table">' . esc($row['email']) . '</div>' : '')
                . '</div></a>';

            $ratingValue = ($row['average_rating'] !== null && $row['average_rating'] !== '') ? sprintf('%0.1f', (float) $row['average_rating']) : '0.0';
            $ratingDisplay = '<i class="fa-solid fa-star text-warning"></i> ' . $ratingValue;
            if ((int) ($row['total_reviews'] ?? 0) > 0) {
                $ratingDisplay .= ' (' . (int) $row['total_reviews'] . ')';
            }

            $isActive = (int) $row['active'] === 1;
            $statusBadge = $isActive
                ? "<span class='badge badge-success'>" . labels('active', 'Active') . "</span>"
                : "<span class='badge badge-danger'>" . labels('inactive', 'Inactive') . "</span>";

            // $isAvailable = (int) $row['is_available'] === 1;
            // $availLabel = $isAvailable ? labels('mark_unavailable', 'Mark Unavailable') : labels('mark_available', 'Mark Available');
            // $availIcon = $isAvailable ? 'fa-toggle-on text-success' : 'fa-toggle-off text-secondary';
            $statusLabel = $isActive ? labels('deactivate', 'Deactivate') : labels('activate', 'Activate');
            $statusIcon = $isActive ? 'fa-ban text-warning' : 'fa-check text-success';

            $operations = '<div class="dropdown">'
                . '<a href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
                . '<button class="btn btn-secondary btn-sm px-3"><i class="fas fa-ellipsis-v"></i></button>'
                . '</a>'
                . '<div class="dropdown-menu dropdown-menu-right">'
                . '<a class="dropdown-item" href="' . $viewUrl . '">'
                . '<i class="fa fa-eye text-success mr-1"></i>' . labels('view_handyman', 'View Handyman') . '</a>'
                . '<a class="dropdown-item handyman-edit" href="#">'
                . '<i class="fa fa-pen text-primary mr-1"></i>' . labels('edit', 'Edit') . '</a>'
                . '<a class="dropdown-item handyman-toggle-status" href="#" data-active="' . (int) $row['active'] . '">'
                . '<i class="fas ' . $statusIcon . ' mr-1"></i>' . $statusLabel . '</a>'
                . '<a class="dropdown-item handyman-delete text-danger" href="#">'
                . '<i class="fas fa-trash-alt mr-1"></i>' . labels('delete', 'Delete') . '</a>'
                . '</div></div>';
            // . '<a class="dropdown-item handyman-toggle-availability" href="#" data-available="' . (int) $row['is_available'] . '">'
            // . '<i class="fas ' . $availIcon . ' mr-1"></i>' . $availLabel . '</a>'

            // $availabilityBadge = $isAvailable
            //     ? "<span class='badge badge-success'>" . labels('available', 'Available') . "</span>"
            //     : "<span class='badge badge-secondary'>" . labels('unavailable', 'Unavailable') . "</span>";

            $formatted[] = [
                'id' => $id,
                'profile' => $profileCard,
                'username' => esc($row['username']),
                'phone' => esc(trim($row['country_code'] . ' ' . $row['phone'])),
                'email' => esc($row['email'] ?? ''),
                'status_badge' => $statusBadge,
                // 'availability_badge' => $availabilityBadge,
                'rating_display' => $ratingDisplay,
                'completed_bookings_display' => (int) ($row['completed_bookings_count'] ?? 0),
                'joined_on_display' => !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '—',
                'salary_display' => $row['salary'] !== null ? esc($row['salary']) : '—',
                'address_display' => !empty($row['address']) ? esc($row['address']) : '—',
                'operations' => $operations,
                // Raw data for edit modal prefill — not displayed as columns
                'country_code' => $row['country_code'],
                'phone_number' => $row['phone'],
                'address' => $row['address'] ?? '',
                'salary' => $row['salary'] !== null ? $row['salary'] : '',
                'active' => (int) $row['active'],
                // 'is_available' => (int) $row['is_available'],
                'image_url' => !empty($row['image']) ? $imageUrl : '',
                'translations' => $translationMap[$id] ?? (object) [],
                'custom_field_values' => $customFieldMap[$id] ?? (object) [],
            ];
        }

        if ($fromApp) {
            return ['total' => (int) $total, 'data' => $formatted];
        }

        return ['total' => (int) $total, 'rows' => $formatted];
    }
}
