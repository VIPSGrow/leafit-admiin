<?php

namespace App\Controllers\Admin;

class HandymenController extends Admin
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function index()
    {
        try {
            helper('function');
            $this->data['currency'] = get_currency();
            setPageInfo($this->data, labels('handymen', 'Handymen') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'handymen_list');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> HandymenController::index()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }

    public function list()
    {
        try {
            $limit = (int) ($this->request->getGet('limit') ?: 10);
            $offset = (int) ($this->request->getGet('offset') ?: 0);
            $search = trim((string) ($this->request->getGet('search') ?: ''));
            $statusRaw = $this->request->getGet('status');
            $statusFilter = ($statusRaw !== null && $statusRaw !== '') ? (int) $statusRaw : null;

            $sort = $this->request->getGet('sort') ?: 'u.id';
            $order = $this->request->getGet('order') ?: 'DESC';

            $allowedSorts = ['u.id', 'u.username', 'u.phone', 'u.email', 'pd.company_name'];
            if (!in_array($sort, $allowedSorts, true)) {
                $sort = 'u.id';
            }
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

            $db = \Config\Database::connect();
            $base = $db->table('users u')
                ->join('handyman_details hd', 'hd.handyman_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id AND ug.group_id = 4')
                ->join('partner_details pd', 'pd.partner_id = hd.partner_id', 'left')
                ->where('hd.deleted_at IS NULL', null, false)
                ->groupBy('u.id');

            if ($search !== '') {
                $base->groupStart()
                    ->like('u.username', $search)
                    ->orLike('u.phone', $search)
                    ->orLike('u.email', $search)
                    ->orLike('pd.company_name', $search)
                    ->groupEnd();
            }

            if ($statusFilter !== null) {
                $base->where('u.active', $statusFilter);
            }

            $total = (clone $base)->select('COUNT(u.id) AS total')->get()->getRow()->total ?? 0;

            $rows = $base
                ->select("u.id, u.username, u.phone, u.country_code, u.email, u.image, u.active, hd.partner_id, hd.salary, pd.company_name,
                    (SELECT COUNT(*) FROM booking_handymen bh WHERE bh.handyman_id = u.id AND bh.status IN ('booking_ended','completed')) AS completed_bookings_count")
                ->orderBy($sort, $order)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();

            $permissionService = new \App\Services\utility\PermissionService();
            $canUpdate = $permissionService->can($this->userId, 'update', 'handyman');

            $fileService = service('fileService');
            $formatted = [];

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $imageUrl = $fileService->url($row['image'] ?? null, 'profile');
                $viewUrl = base_url('admin/handymen/view/' . $id);

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
                $profileImg = '<a href="' . $viewUrl . '" title="' . labels('view_handyman', 'View Handyman') . '">' . $profileThumb . '</a>';

                $isActive = (int) $row['active'] === 1;
                $statusBadge = $isActive
                    ? "<span class='badge badge-success'>" . labels('active', 'Active') . "</span>"
                    : "<span class='badge badge-danger'>" . labels('inactive', 'Inactive') . "</span>";

                $statusLabel = $isActive ? labels('deactivate', 'Deactivate') : labels('activate', 'Activate');
                $statusIcon = $isActive ? 'fa-ban' : 'fa-check';
                $statusBtnClass = $isActive ? 'btn-outline-warning' : 'btn-outline-success';

                $toggleBtn = $canUpdate
                    ? '<a href="#" class="btn ' . $statusBtnClass . ' btn-sm hm-global-toggle-status mr-1" '
                    . 'data-active="' . (int) $isActive . '" title="' . $statusLabel . '">'
                    . '<i class="fas ' . $statusIcon . '"></i></a>'
                    : '';

                $operations = $toggleBtn
                    . '<a href="' . $viewUrl . '" class="btn btn-primary btn-sm" title="' . labels('view_handyman', 'View Handyman') . '">'
                    . '<i class="fa fa-eye"></i></a>';

                $usernameCell = '<a href="' . $viewUrl . '" class="text-decoration-none hm-global-username-link">'
                    . esc($row['username']) . '</a>';

                $phoneDisplay = esc(trim($row['country_code'] . ' ' . $row['phone']));
                $emailDisplay = esc($row['email'] ?? '');

                $profileCard = '<a href="' . $viewUrl . '" class="o-media o-media--middle text-decoration-none">'
                    . $profileThumb
                    . '<div class="o-media__body">'
                    . '<div class="provider_name_table">' . esc($row['username'] ?? '') . '</div>'
                    . '<div class="provider_email_table">' . $phoneDisplay . '</div>'
                    . ($emailDisplay !== '' ? '<div class="provider_email_table">' . $emailDisplay . '</div>' : '')
                    . '</div></a>';

                $formatted[] = [
                    'id' => $id,
                    'profile' => $profileCard,
                    'profile_image' => $profileImg,
                    'username' => $usernameCell,
                    'phone' => $phoneDisplay,
                    'email' => $emailDisplay,
                    'provider' => esc($row['company_name'] ?? ''),
                    'status_badge' => $statusBadge,
                    'salary_display' => $row['salary'] !== null ? esc($row['salary']) : '—',
                    'completed_bookings_display' => (int) ($row['completed_bookings_count'] ?? 0),
                    'operations' => $operations,
                    'active' => (int) $row['active'],
                    'partner_id' => (int) $row['partner_id'],
                ];
            }

            return $this->response->setJSON(['total' => (int) $total, 'rows' => $formatted]);

        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> HandymenController::list()');
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    public function view($id = 0)
    {
        try {
            helper('function');
            $handymanId = (int) $id;
            $handymanModel = new \App\Models\HandymanDetailsModel();
            $handyman = $handymanModel->getForAdminView($handymanId);

            if (empty($handyman)) {
                return redirect('admin/handymen');
            }

            $this->data['handyman'] = $handyman;
            $this->data['currency'] = get_currency();
            $this->data['handyman_custom_fields'] = $handymanModel->getCustomFieldsForView($handymanId);

            setPageInfo($this->data, labels('view_handyman', 'View Handyman') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'view_handyman');
            return view('backend/admin/template', $this->data);

        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/HandymenController.php - view()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return redirect('admin/handymen');
        }
    }

    public function overview($id = 0)
    {
        try {
            $handymanId = (int) $id;
            $handymanModel = new \App\Models\HandymanDetailsModel();
            $handyman = $handymanModel->getForAdminView($handymanId);
            if (empty($handyman)) {
                return $this->response->setJSON(['error' => true]);
            }

            $detail = $handymanModel->where('handyman_id', $handymanId)->first();
            $bookingModel = model(\App\Models\BookingHandymenModel::class);

            $db = \Config\Database::connect();
            $completedCount = $db->table('booking_handymen bh')
                ->whereIn('bh.status', ['booking_ended', 'completed'])
                ->where('bh.handyman_id', $handymanId)
                ->countAllResults();

            $recentCompleted = $bookingModel->listForHandyman($handymanId, 5, 0, '')['data'];
            $recentCompleted = array_values(array_filter($recentCompleted, fn($row) => (int) $row['status_group'] === 2));

            $today = \CodeIgniter\I18n\Time::today();
            $bookingSummary = $bookingModel->getDashboardSummary(
                $handymanId,
                $today->toDateString(),
                $today->addDays(1)->toDateString(),
                $today->addDays(2)->toDateString()
            );

            $activeBooking = $db->table('booking_handymen bh')
                ->select('bh.order_id, o.order_latitude, o.order_longitude, o.address')
                ->join('orders o', 'o.id = bh.order_id')
                ->where('bh.handyman_id', $handymanId)
                ->where('bh.status', 'on_the_way')
                ->orderBy('bh.id', 'DESC')
                ->get()->getRowArray();

            $map = ['available' => false];
            if (!empty($activeBooking) && !empty($activeBooking['order_latitude']) && !empty($activeBooking['order_longitude'])) {
                $liveTracking = model(\App\Models\LiveTrackingModel::class)
                    ->select('latitude, longitude, updated_at')
                    ->where('order_id', $activeBooking['order_id'])
                    ->first();

                if (!empty($liveTracking)) {
                    $map = [
                        'available' => true,
                        'order_id' => (int) $activeBooking['order_id'],
                        'order_latitude' => (float) $activeBooking['order_latitude'],
                        'order_longitude' => (float) $activeBooking['order_longitude'],
                        'order_address' => $activeBooking['address'],
                        'handyman_latitude' => (float) $liveTracking['latitude'],
                        'handyman_longitude' => (float) $liveTracking['longitude'],
                    ];
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'salary' => $detail['salary'] ?? null,
                'total_bookings' => $bookingSummary['total_bookings'],
                'lead_bookings' => $bookingSummary['lead_bookings'],
                'bookings' => [
                    'today_bookings' => $bookingSummary['today_bookings'],
                    'tomorrow_bookings' => $bookingSummary['tomorrow_bookings'],
                    'upcoming_bookings' => $bookingSummary['upcoming_bookings'] + $bookingSummary['tomorrow_bookings'],
                ],
                'completed_bookings_count' => (int) $completedCount,
                'recent_completed_bookings' => $recentCompleted,
                'average_rating' => (float) ($detail['average_rating'] ?? 0),
                'total_reviews' => (int) ($detail['total_reviews'] ?? 0),
                'map' => $map,
            ]);

        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/HandymenController.php - overview()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true]);
        }
    }

    public function trackerLocation($id = 0)
    {
        try {
            $handymanId = (int) $id;
            $handymanModel = new \App\Models\HandymanDetailsModel();
            if (empty($handymanModel->getForAdminView($handymanId))) {
                return $this->response->setJSON(['error' => true])->setStatusCode(403);
            }

            $db = \Config\Database::connect();
            $activeBooking = $db->table('booking_handymen bh')
                ->select('bh.order_id, bh.status AS handyman_status')
                ->where('bh.handyman_id', $handymanId)
                ->where('bh.status', 'on_the_way')
                ->orderBy('bh.id', 'DESC')
                ->get()->getRowArray();

            if (empty($activeBooking)) {
                return $this->response->setJSON(['error' => true, 'message' => 'no_active_booking']);
            }

            $liveTracking = model(\App\Models\LiveTrackingModel::class)
                ->select('latitude, longitude, updated_at')
                ->where('order_id', $activeBooking['order_id'])
                ->first();

            if (empty($liveTracking)) {
                return $this->response->setJSON(['error' => true, 'message' => 'no_data', 'handyman_status' => $activeBooking['handyman_status']]);
            }

            return $this->response->setJSON([
                'error' => false,
                'latitude' => (float) $liveTracking['latitude'],
                'longitude' => (float) $liveTracking['longitude'],
                'updated_at' => $liveTracking['updated_at'],
                'handyman_status' => $activeBooking['handyman_status'],
            ]);

        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/HandymenController.php - trackerLocation()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true])->setStatusCode(500);
        }
    }

    public function bookingsList($id = 0)
    {
        try {
            $handymanId = (int) $id;
            $handymanModel = new \App\Models\HandymanDetailsModel();
            if (empty($handymanModel->getForAdminView($handymanId))) {
                return $this->response->setJSON(['total' => 0, 'rows' => []]);
            }

            $limit = (int) ($this->request->getGet('limit') ?: 10);
            $offset = (int) ($this->request->getGet('offset') ?: 0);
            $search = trim((string) ($this->request->getGet('search') ?: ''));

            $bookingModel = model(\App\Models\BookingHandymenModel::class);
            $result = $bookingModel->listForHandyman($handymanId, $limit, $offset, $search);
            $currency = get_currency();

            $groupLabels = [
                0 => ['text' => labels('ongoing', 'Ongoing'), 'class' => 'badge-info'],
                1 => ['text' => labels('upcoming', 'Upcoming'), 'class' => 'badge-warning'],
                2 => ['text' => labels('completed', 'Completed'), 'class' => 'badge-success'],
            ];

            $rows = [];
            foreach ($result['data'] as $row) {
                $group = (int) $row['status_group'];
                $badge = $groupLabels[$group] ?? $groupLabels[2];

                $isAtStore = (int) ($row['address_id'] ?? 1) === 0;
                $serviceTypeBadge = $isAtStore
                    ? '<span class="badge badge-secondary">' . labels('at_store', 'At Store') . '</span>'
                    : '<span class="badge badge-primary">' . labels('at_doorstep', 'At Doorstep') . '</span>';
                $address = $isAtStore ? ($row['provider_address'] ?? $row['address'] ?? '') : ($row['address'] ?? '');

                $rows[] = [
                    'order_id' => (int) $row['order_id'],
                    'customer_name' => esc($row['customer_name'] ?? '-'),
                    'status_badge' => '<span class="badge ' . $badge['class'] . '">' . $badge['text'] . '</span>',
                    'service_type_badge' => $serviceTypeBadge,
                    'scheduled_display' => !empty($row['date_of_service'])
                        ? date('M d, Y', strtotime($row['date_of_service'])) . ' ' . substr((string) $row['starting_time'], 0, 5)
                        : '-',
                    'amount_display' => $currency . ' ' . number_format((float) $row['final_total'], 2),
                    'address_display' => !empty($address) ? esc($address) : '-',
                    'operations' => '<a href="' . base_url('admin/orders/veiw_orders/' . (int) $row['order_id']) . '" class="btn btn-primary btn-sm" title="' . labels('view_order', 'View Order') . '"><i class="fa fa-eye"></i></a>',
                ];
            }

            return $this->response->setJSON(['total' => $result['total'], 'rows' => $rows]);

        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/HandymenController.php - bookingsList()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    public function reviewsList($id = 0)
    {
        try {
            $handymanId = (int) $id;
            $handymanModel = new \App\Models\HandymanDetailsModel();
            if (empty($handymanModel->getForAdminView($handymanId))) {
                return $this->response->setJSON(['total' => 0, 'rows' => []]);
            }

            $limit = (int) ($this->request->getGet('limit') ?: 10);
            $offset = (int) ($this->request->getGet('offset') ?: 0);
            $search = trim((string) ($this->request->getGet('search') ?: ''));

            $reviewModel = model(\App\Models\HandymanReviewModel::class);
            $result = $reviewModel->listForHandyman($handymanId, $limit, $offset, $search);
            $fileService = service('fileService');

            $rows = [];
            foreach ($result['data'] as $row) {
                $rating = max(0, min(5, (int) $row['rating']));
                $stars = '<div class="text-warning" title="' . $rating . '/5">';
                for ($i = 1; $i <= 5; $i++) {
                    $stars .= '<i class="fa' . ($i <= $rating ? 's' : 'r') . ' fa-star"></i>';
                }
                $stars .= '</div>';

                $customerImage = $fileService->url($row['customer_image'] ?? '', 'profile');
                $images = [];
                if (!empty($row['images'])) {
                    $paths = is_string($row['images']) ? (json_decode($row['images'], true) ?: []) : (array) $row['images'];
                    $images = array_map(fn($p) => $fileService->url($p, 'handyman_reviews'), $paths);
                }

                $rows[] = [
                    'customer_cell' => '<div class="d-flex align-items-center">'
                        . '<img src="' . esc($customerImage) . '" class="rounded-circle mr-2" width="36" height="36" style="object-fit: cover;">'
                        . '<span>' . esc($row['customer_name'] ?? '-') . '</span></div>',
                    'rating_stars' => $stars,
                    'review_text' => !empty($row['review']) ? esc($row['review']) : '<span class="text-muted">-</span>',
                    'images_cell' => !empty($images)
                        ? '<button type="button" class="btn btn-sm btn-light border view-review-images" data-images=\'' . json_encode($images) . '\'>'
                        . '<i class="fas fa-images"></i> ' . labels('view_images', 'View Images') . '</button>'
                        : '<span class="text-muted">' . labels('no_images', 'No Images') . '</span>',
                    'rated_on' => !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : '-',
                ];
            }

            return $this->response->setJSON(['total' => $result['total'], 'rows' => $rows]);

        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/HandymenController.php - reviewsList()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }
    }

    public function toggleStatus()
    {
        try {
            $handymanId = (int) ($this->request->getPost('id') ?: 0);
            if ($handymanId <= 0) {
                return JsonError(labels('invalid_id'));
            }

            $handymanModel = new \App\Models\HandymanDetailsModel();
            $detail = $handymanModel->where('handyman_id', $handymanId)->first();
            if (empty($detail)) {
                return JsonError(labels('data_not_found'));
            }

            $usersModel = new \App\Models\Users_model();
            $user = $usersModel->find($handymanId);
            $newActive = ((int) $user['active'] === 1) ? 0 : 1;
            $usersModel->update($handymanId, ['active' => $newActive]);

            return JsonSuccess($newActive === 1 ? labels('activated_successfully') : labels('deactivated_successfully'));

        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> HandymenController::toggleStatus()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }

    public function delete()
    {
        try {
            $handymanId = (int) ($this->request->getPost('id') ?: 0);
            if ($handymanId <= 0) {
                return JsonError(labels('invalid_id'));
            }

            $handymanModel = new \App\Models\HandymanDetailsModel();
            $detail = $handymanModel->where('handyman_id', $handymanId)->first();
            if (empty($detail)) {
                return JsonError(labels('data_not_found'));
            }

            $usersModel = new \App\Models\Users_model();
            $translatedModel = new \App\Models\TranslatedHandymanDetailsModel();
            $cfValuesModel = new \App\Models\HandymanCustomFieldValuesModel();
            $userData = $usersModel->find($handymanId);

            $db = \Config\Database::connect();
            $db->transStart();

            $handymanModel->where('handyman_id', $handymanId)->delete();
            $translatedModel->where('handyman_id', $handymanId)->delete();
            $cfValuesModel->where('handyman_id', $handymanId)->delete();
            $usersModel->delete($handymanId);

            $db->transComplete();

            if (!$db->transStatus()) {
                return JsonError(labels(ERROR_OCCURED));
            }

            $fileService = new \App\Services\utility\FileService();
            if (!empty($userData['image'])) {
                $fileService->delete('profile', $userData['image']);
            }

            return JsonSuccess(labels('data_deleted_successfully'));

        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> HandymenController::delete()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }
}
