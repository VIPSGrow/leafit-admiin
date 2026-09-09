<?php

namespace App\Controllers\Partner;

use App\Models\BookingHandymenModel;
use App\Models\Country_code_model;
use App\Models\HandymanCashCollectionModel;
use App\Models\HandymanCustomFieldValuesModel;
use App\Models\HandymanDetailsModel;
use App\Models\HandymanReviewModel;
use App\Models\Language_model;
use App\Models\LiveTrackingModel;
use App\Models\TranslatedHandymanDetailsModel;
use App\Models\UsersGroupsModel;
use App\Models\Users_model;
use App\Services\utility\FileService;
use CodeIgniter\I18n\Time;

class Handyman extends Partner
{
    private HandymanDetailsModel $handymanDetailsModel;
    private HandymanCustomFieldValuesModel $cfValuesModel;
    private TranslatedHandymanDetailsModel $translatedModel;
    private Users_model $usersModel;
    private UsersGroupsModel $usersGroupsModel;
    private Language_model $languageModel;
    private FileService $fileService;

    public function __construct()
    {
        helper('ResponceServices');
        parent::__construct();
        $this->handymanDetailsModel = new HandymanDetailsModel();
        $this->cfValuesModel = new HandymanCustomFieldValuesModel();
        $this->translatedModel = new TranslatedHandymanDetailsModel();
        $this->usersModel = new Users_model();
        $this->usersGroupsModel = new UsersGroupsModel();
        $this->languageModel = new Language_model();
        $this->fileService = new FileService();
    }

    public function index()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        $countryCodeModel = new Country_code_model();
        $country_codes = $countryCodeModel->findAll();
        $system_country_code = $countryCodeModel->where('is_default', 1)->first();
        $default_calling_code = !empty($system_country_code['calling_code']) ? $system_country_code['calling_code'] : '+91';
        $single_country_code = count($country_codes) === 1;

        $this->data['country_codes'] = $country_codes ?: [];
        $this->data['default_calling_code'] = $default_calling_code;
        $this->data['single_country_code'] = $single_country_code;
        $this->data['single_country_data'] = $single_country_code ? $country_codes[0] : null;
        $this->data['handyman_custom_fields'] = $this->getCustomFields();
        $this->data['languages'] = $this->languageModel
            ->select('id, language, is_default, code')
            ->orderBy('id', 'ASC')
            ->findAll();
        helper('function');
        $this->data['password_rules'] = get_password_rules();
        $this->data['currency'] = get_currency();

        setPageInfo($this->data, labels('handymen', 'Handymen') . ' | ' . labels('provider_panel'), 'handymen');

        return view('backend/partner/template', $this->data);
    }

    public function store()
    {
        if (!$this->isLoggedIn) {
            return JsonError(labels('unauthorized'));
        }

        $post = $this->request->getPost();
        $usernamesByLang = is_array($post['username'] ?? null) ? $post['username'] : [];
        $countryCode = trim((string) ($post['country_code'] ?? ''));
        $phone = trim((string) ($post['phone'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        $email = strtolower(trim((string) ($post['email'] ?? '')));
        $address = trim((string) ($post['address'] ?? ''));
        $salaryRaw = trim((string) ($post['salary'] ?? ''));
        $salary = $salaryRaw !== '' ? (float) $salaryRaw : null;
        $status = isset($post['status']) ? 1 : 0;

        if ($salary === null) {
            return JsonError(labels('salary_required', 'Salary is required'));
        }

        // Resolve default-language username
        $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
        $defaultLangCode = '';
        foreach ($languages as $l) {
            if (!empty($l['is_default'])) {
                $defaultLangCode = $l['code'];
                break;
            }
        }
        $username = trim((string) ($usernamesByLang[$defaultLangCode] ?? ''));

        // Validate required fields
        if ($username === '') {
            return JsonError(labels('default_language_username_required'));
        }
        if ($phone === '') {
            return JsonError(labels('enter_mobile_number'));
        }
        if ($password === '') {
            return JsonError(labels('password'));
        }

        $passwordValidationErrors = validate_password_strength($password);

        if (!empty($passwordValidationErrors)) {
            return JsonError($passwordValidationErrors);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return JsonError(labels('please_enter_valid_email'));
        }

        $profileImageFile = $this->request->getFile('profile_image');
        if (!$profileImageFile || !$profileImageFile->isValid()) {
            return JsonError(labels('image_required'));
        }

        // Uniqueness checks via model
        if ($this->usersModel->where('phone', $phone)->where('country_code', $countryCode)->countAllResults() > 0) {
            return JsonError(labels('phone_already_registered'));
        }
        if ($email !== '' && $this->usersModel->where('email', $email)->countAllResults() > 0) {
            return JsonError(labels('email_already_registered'));
        }

        // Hash password (bcrypt cost 10 — same as ionAuth default)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        if (!$hashedPassword) {
            return JsonError(labels(ERROR_OCCURED));
        }

        // Upload files before the transaction (filesystem ops are not transactional)
        $profileUpload = $this->fileService->upload($profileImageFile, 'profile');
        if ($profileUpload['error']) {
            return JsonError(labels('failed_to_upload_image'));
        }
        $profilePath = $profileUpload['path'];

        $customFields = $this->getCustomFields();
        $cfFilePaths = [];
        foreach ($customFields as $field) {
            if ($field['field_type'] !== 'file') {
                continue;
            }
            $cfFile = $this->request->getFile('cf_' . $field['id']);
            if ($cfFile && $cfFile->isValid()) {
                $cfUpload = $this->fileService->upload($cfFile, 'custom_fields');
                $cfFilePaths[$field['id']] = $cfUpload['error'] ? null : $cfUpload['path'];
            } else {
                $cfFilePaths[$field['id']] = null;
            }
        }

        // Wrap all DB writes in a single transaction
        $db = \Config\Database::connect();
        $db->transStart();

        $newUserId = $this->usersModel->insert([
            'username' => $username,
            'phone' => $phone,
            'country_code' => $countryCode,
            'email' => $email !== '' ? $email : null,
            'password' => $hashedPassword,
            'active' => $status,
            'ip_address' => $this->request->getIPAddress(),
            'created_on' => time(),
            'image' => $profilePath,
            'api_key' => bin2hex(random_bytes(16)),
        ]);

        if ($newUserId) {
            $this->usersGroupsModel->insert(['user_id' => $newUserId, 'group_id' => 4]);

            $this->handymanDetailsModel->insert([
                'handyman_id' => $newUserId,
                'partner_id' => $this->userId,
                'address' => $address !== '' ? $address : null,
                'salary' => $salary,
                'is_available' => 1,
            ]);

            foreach ($languages as $lang) {
                $isDefault = !empty($lang['is_default']);
                $value = $isDefault ? $username : trim((string) ($usernamesByLang[$lang['code']] ?? ''));
                if ($value === '') {
                    continue;
                }
                $this->translatedModel->insert([
                    'handyman_id' => $newUserId,
                    'language_id' => (int) $lang['id'],
                    'username' => $value,
                ]);
            }

            foreach ($customFields as $field) {
                $cfId = (int) $field['id'];

                if ($field['field_type'] === 'file') {
                    $value = $cfFilePaths[$cfId] ?? null;
                } else {
                    $raw = isset($post['cf_' . $cfId]) ? trim((string) $post['cf_' . $cfId]) : '';
                    $value = $raw !== '' ? $raw : null;
                }

                if ($value !== null || !empty($field['required'])) {
                    $this->cfValuesModel->insert([
                        'handyman_id' => $newUserId,
                        'custom_field_id' => $cfId,
                        'value' => $value,
                    ]);
                }
            }
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            $this->fileService->delete('profile', $profilePath);
            foreach ($cfFilePaths as $path) {
                if ($path !== null) {
                    $this->fileService->delete('custom_fields', $path);
                }
            }
            return JsonError(labels(ERROR_OCCURED));
        }

        return JsonSuccess(DATA_SAVED_SUCCESSFULLY);
    }

    public function update()
    {
        if (!$this->isLoggedIn) {
            return JsonError(labels('unauthorized'));
        }

        $post = $this->request->getPost();
        $handymanId = (int) ($post['handyman_id'] ?? 0);

        if ($handymanId <= 0) {
            return JsonError(labels('invalid_id'));
        }

        // Ownership check
        $detail = $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->where('partner_id', $this->userId)
            ->first();

        if (empty($detail)) {
            return JsonError(labels('data_not_found'));
        }

        $usernamesByLang = is_array($post['username'] ?? null) ? $post['username'] : [];
        $countryCode = trim((string) ($post['country_code'] ?? ''));
        $phone = trim((string) ($post['phone'] ?? ''));
        $email = strtolower(trim((string) ($post['email'] ?? '')));
        $address = trim((string) ($post['address'] ?? ''));
        $salaryRaw = trim((string) ($post['salary'] ?? ''));
        $salary = $salaryRaw !== '' ? (float) $salaryRaw : null;
        $status = isset($post['status']) ? 1 : 0;

        if ($salary === null) {
            return JsonError(labels('salary_required', 'Salary is required'));
        }

        $languages = $this->languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
        $defaultLangCode = '';
        foreach ($languages as $l) {
            if (!empty($l['is_default'])) {
                $defaultLangCode = $l['code'];
                break;
            }
        }
        $username = trim((string) ($usernamesByLang[$defaultLangCode] ?? ''));

        if ($username === '') {
            return JsonError(labels('default_language_username_required'));
        }
        if ($phone === '') {
            return JsonError(labels('enter_mobile_number'));
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return JsonError(labels('please_enter_valid_email'));
        }

        // Uniqueness checks — exclude current handyman
        $phoneTaken = $this->usersModel
            ->where('phone', $phone)
            ->where('country_code', $countryCode)
            ->where('id !=', $handymanId)
            ->countAllResults() > 0;
        if ($phoneTaken) {
            return JsonError(labels('phone_already_registered'));
        }

        if ($email !== '') {
            $emailTaken = $this->usersModel
                ->where('email', $email)
                ->where('id !=', $handymanId)
                ->countAllResults() > 0;
            if ($emailTaken) {
                return JsonError(labels('email_already_registered'));
            }
        }

        // Handle profile image upload (optional on update)
        $existingUser = $this->usersModel->find($handymanId);
        $oldProfilePath = $existingUser['image'] ?? null;
        $profilePath = $oldProfilePath;
        $newProfilePath = null;

        $profileImageFile = $this->request->getFile('profile_image');
        if ($profileImageFile && $profileImageFile->isValid()) {
            $profileUpload = $this->fileService->upload($profileImageFile, 'profile');
            if ($profileUpload['error']) {
                return JsonError(labels('failed_to_upload_image'));
            }
            $newProfilePath = $profileUpload['path'];
            $profilePath = $newProfilePath;
        }

        // Handle custom field file uploads
        $customFields = $this->getCustomFields();
        $cfFilePaths = [];
        foreach ($customFields as $field) {
            if ($field['field_type'] !== 'file') {
                continue;
            }
            $cfFile = $this->request->getFile('cf_' . $field['id']);
            if ($cfFile && $cfFile->isValid()) {
                $cfUpload = $this->fileService->upload($cfFile, 'custom_fields');
                $cfFilePaths[$field['id']] = $cfUpload['error'] ? null : $cfUpload['path'];
            }
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $this->usersModel->update($handymanId, [
            'username' => $username,
            'phone' => $phone,
            'country_code' => $countryCode,
            'email' => $email !== '' ? $email : null,
            'active' => $status,
            'image' => $profilePath,
        ]);

        $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->set(['address' => $address !== '' ? $address : null, 'salary' => $salary])
            ->update();

        foreach ($languages as $lang) {
            $isDefault = !empty($lang['is_default']);
            $value = $isDefault ? $username : trim((string) ($usernamesByLang[$lang['code']] ?? ''));

            if ($value === '') {
                continue;
            }

            $existing = $this->translatedModel
                ->where('handyman_id', $handymanId)
                ->where('language_id', (int) $lang['id'])
                ->first();

            if ($existing) {
                $this->translatedModel->update($existing['id'], ['username' => $value]);
            } else {
                $this->translatedModel->insert([
                    'handyman_id' => $handymanId,
                    'language_id' => (int) $lang['id'],
                    'username' => $value,
                ]);
            }
        }

        foreach ($customFields as $field) {
            $cfId = (int) $field['id'];

            if ($field['field_type'] === 'file') {
                if (!isset($cfFilePaths[$cfId])) {
                    continue;
                }
                $value = $cfFilePaths[$cfId];
            } else {
                $raw = isset($post['cf_' . $cfId]) ? trim((string) $post['cf_' . $cfId]) : '';
                $value = $raw !== '' ? $raw : null;
            }

            $existingCf = $this->cfValuesModel
                ->where('handyman_id', $handymanId)
                ->where('custom_field_id', $cfId)
                ->first();

            if ($existingCf) {
                $this->cfValuesModel->update($existingCf['id'], ['value' => $value]);
            } elseif ($value !== null) {
                $this->cfValuesModel->insert([
                    'handyman_id' => $handymanId,
                    'custom_field_id' => $cfId,
                    'value' => $value,
                ]);
            }
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            if ($newProfilePath !== null) {
                $this->fileService->delete('profile', $newProfilePath);
            }
            foreach ($cfFilePaths as $path) {
                if ($path !== null) {
                    $this->fileService->delete('custom_fields', $path);
                }
            }
            return JsonError(labels(ERROR_OCCURED));
        }

        // Delete old profile image after successful transaction
        if ($newProfilePath !== null && $oldProfilePath !== null) {
            $this->fileService->delete('profile', $oldProfilePath);
        }

        return JsonSuccess(DATA_UPDATED_SUCCESSFULLY);
    }

    public function list()
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $limit = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $sort = $this->request->getGet('sort') ?: 'u.id';
        $order = $this->request->getGet('order') ?: 'DESC';
        $search = trim((string) ($this->request->getGet('search') ?: ''));

        $allowedSorts = ['u.id', 'u.username', 'u.phone', 'u.email'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'u.id';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $result = $this->handymanDetailsModel->list($this->userId, false, $search, $limit, $offset, $sort, $order);

        return $this->response->setJSON($result);
    }

    public function view($id = 0)
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        helper('function');
        $handymanId = (int) $id;
        $detail = $this->getOwnedHandyman($handymanId);

        if (empty($detail)) {
            return redirect('partner/handymen');
        }

        $user = $this->usersModel->find($handymanId);

        $this->data['handyman'] = [
            'id' => $handymanId,
            'username' => $user['username'] ?? '',
            'phone' => trim(($user['country_code'] ?? '') . ' ' . ($user['phone'] ?? '')),
            'email' => $user['email'] ?? '',
            'image' => $this->fileService->url($user['image'] ?? '', 'profile'),
            'active' => (int) ($user['active'] ?? 0),
            // 'is_available' => (int) $detail['is_available'],
            'address' => $detail['address'] ?? '',
        ];
        $this->data['currency'] = get_currency();
        $this->data['handyman_custom_fields'] = $this->handymanDetailsModel->getCustomFieldsForView($handymanId);

        setPageInfo($this->data, labels('view_handyman', 'View Handyman') . ' | ' . labels('provider_panel'), 'view_handyman');

        return view('backend/partner/template', $this->data);
    }

    public function overview($id = 0)
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['error' => true]);
        }

        $handymanId = (int) $id;
        $detail = $this->getOwnedHandyman($handymanId);
        if (empty($detail)) {
            return $this->response->setJSON(['error' => true]);
        }

        $cashModel = model(HandymanCashCollectionModel::class);
        $bookingModel = model(BookingHandymenModel::class);

        $db = \Config\Database::connect();
        $completedCount = $db->table('booking_handymen bh')
            ->whereIn('bh.status', ['booking_ended', 'completed'])
            ->where('bh.handyman_id', $handymanId)
            ->countAllResults();

        $recentCompleted = $bookingModel->listForHandyman($handymanId, 5, 0, '')['data'];
        $recentCompleted = array_values(array_filter($recentCompleted, fn($row) => (int) $row['status_group'] === 2));

        $today = Time::today();
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
            $liveTracking = model(LiveTrackingModel::class)
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
            'total_cash_collected' => number_format($cashModel->getOverallTotal($handymanId), 2),
            'outstanding_balance' => number_format($cashModel->getOutstandingTotal($handymanId), 2),
            'completed_bookings_count' => (int) $completedCount,
            'recent_completed_bookings' => $recentCompleted,
            'average_rating' => (float) ($detail['average_rating'] ?? 0),
            'total_reviews' => (int) ($detail['total_reviews'] ?? 0),
            'map' => $map,
        ]);
    }

    public function trackerLocation($id = 0)
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['error' => true])->setStatusCode(403);
        }

        $handymanId = (int) $id;
        $detail = $this->getOwnedHandyman($handymanId);
        if (empty($detail)) {
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

        $liveTracking = model(LiveTrackingModel::class)
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
    }

    public function bookingsList($id = 0)
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $handymanId = (int) $id;
        $detail = $this->getOwnedHandyman($handymanId);
        if (empty($detail)) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $limit = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $search = trim((string) ($this->request->getGet('search') ?: ''));

        $bookingModel = model(BookingHandymenModel::class);
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
                'operations' => '<a href="' . base_url('partner/orders/veiw_orders/' . (int) $row['order_id']) . '" class="btn btn-primary btn-sm" title="' . labels('view_order', 'View Order') . '"><i class="fa fa-eye"></i></a>',
            ];
        }

        return $this->response->setJSON(['total' => $result['total'], 'rows' => $rows]);
    }

    public function cashCollectionList($id = 0)
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $handymanId = (int) $id;
        $detail = $this->getOwnedHandyman($handymanId);
        if (empty($detail)) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $limit = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $search = trim((string) ($this->request->getGet('search') ?: ''));
        $sort = $this->request->getGet('sort') ?: 'hcc.id';
        $order = $this->request->getGet('order') ?: 'DESC';

        $cashModel = model(HandymanCashCollectionModel::class);
        $rows = $cashModel->list($handymanId, $limit, $offset, $search, $sort, $order);
        $total = $cashModel->countList($handymanId, $search);
        $currency = get_currency();

        $formatted = [];
        foreach ($rows as $row) {
            $isCollection = $row['type'] === 'collection';
            $formatted[] = [
                'order_id' => $row['order_id'] ? (int) $row['order_id'] : '-',
                'customer_name' => esc($row['customer_name'] ?? '-'),
                'type_badge' => $isCollection
                    ? '<span class="badge badge-success">' . labels('collection', 'Collection') . '</span>'
                    : '<span class="badge badge-secondary">' . labels('settlement', 'Settlement') . '</span>',
                'amount_display' => $currency . ' ' . number_format((float) $row['amount'], 2),
                'message' => !empty($row['message']) ? esc($row['message']) : '-',
                'created_at_display' => !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : '-',
            ];
        }

        return $this->response->setJSON(['total' => $total, 'rows' => $formatted]);
    }

    public function reviewsList($id = 0)
    {
        if (!$this->isLoggedIn) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $handymanId = (int) $id;
        $detail = $this->getOwnedHandyman($handymanId);
        if (empty($detail)) {
            return $this->response->setJSON(['total' => 0, 'rows' => []]);
        }

        $limit = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $search = trim((string) ($this->request->getGet('search') ?: ''));

        $reviewModel = model(HandymanReviewModel::class);
        $result = $reviewModel->listForHandyman($handymanId, $limit, $offset, $search);

        $rows = [];
        foreach ($result['data'] as $row) {
            $rating = max(0, min(5, (int) $row['rating']));
            $stars = '<div class="text-warning" title="' . $rating . '/5">';
            for ($i = 1; $i <= 5; $i++) {
                $stars .= '<i class="fa' . ($i <= $rating ? 's' : 'r') . ' fa-star"></i>';
            }
            $stars .= '</div>';

            $customerImage = $this->fileService->url($row['customer_image'] ?? '', 'profile');
            $images = [];
            if (!empty($row['images'])) {
                $paths = is_string($row['images']) ? (json_decode($row['images'], true) ?: []) : (array) $row['images'];
                $images = array_map(fn($p) => $this->fileService->url($p, 'handyman_reviews'), $paths);
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
    }

    public function delete()
    {
        if (!$this->isLoggedIn) {
            return JsonError(labels('unauthorized'));
        }

        $handymanId = (int) ($this->request->getPost('id') ?: 0);
        if ($handymanId <= 0) {
            return JsonError(labels('invalid_id'));
        }

        $detail = $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->where('partner_id', $this->userId)
            ->first();

        if (empty($detail)) {
            return JsonError(labels('data_not_found'));
        }

        $bookingModel = model(BookingHandymenModel::class);
        $activeBookings = $bookingModel->getActiveBookingAssignments($handymanId);
        if (!empty($activeBookings)) {
            return JsonError(
                labels('handyman_has_active_booking', 'This handyman is assigned to a booking that is still in progress and cannot be deleted until it is completed or cancelled.'),
                null,
                ['active_booking' => true, 'bookings' => $this->formatActiveBookings($activeBookings)]
            );
        }

        $userData = $this->usersModel->find($handymanId);

        $db = \Config\Database::connect();
        $db->transStart();

        $this->handymanDetailsModel->where('handyman_id', $handymanId)->delete();
        $this->translatedModel->where('handyman_id', $handymanId)->delete();
        $this->cfValuesModel->where('handyman_id', $handymanId)->delete();
        $this->usersModel->delete($handymanId);

        $db->transComplete();

        if (!$db->transStatus()) {
            return JsonError(labels(ERROR_OCCURED));
        }

        if (!empty($userData['image'])) {
            $this->fileService->delete('profile', $userData['image']);
        }

        return JsonSuccess(labels('data_deleted_successfully'));
    }

    public function toggleStatus()
    {
        if (!$this->isLoggedIn) {
            return JsonError(labels('unauthorized'));
        }

        $handymanId = (int) ($this->request->getPost('id') ?: 0);
        if ($handymanId <= 0) {
            return JsonError(labels('invalid_id'));
        }

        $detail = $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->where('partner_id', $this->userId)
            ->first();

        if (empty($detail)) {
            return JsonError(labels('data_not_found'));
        }

        $user = $this->usersModel->find($handymanId);
        $newActive = ((int) $user['active'] === 1) ? 0 : 1;

        if ($newActive === 0) {
            $bookingModel = model(BookingHandymenModel::class);
            $activeBookings = $bookingModel->getActiveBookingAssignments($handymanId);
            if (!empty($activeBookings)) {
                return JsonError(
                    labels('handyman_has_active_booking', 'This handyman is assigned to a booking that is still in progress and cannot be deleted until it is completed or cancelled.'),
                    null,
                    ['active_booking' => true, 'bookings' => $this->formatActiveBookings($activeBookings)]
                );
            }
        }

        $this->usersModel->update($handymanId, ['active' => $newActive]);

        return JsonSuccess($newActive === 1 ? labels('activated_successfully') : labels('deactivated_successfully'));
    }

    /**
     * GET/POST pre-check used by the panel before it even asks the user to confirm
     * a delete/deactivate — the same check re-run server-side in delete()/toggleStatus()
     * is only a fail-safe against races (e.g. booking assigned after this call returned).
     */
    public function activeBookings()
    {
        if (!$this->isLoggedIn) {
            return JsonError(labels('unauthorized'));
        }

        $handymanId = (int) ($this->request->getPost('id') ?: 0);
        if ($handymanId <= 0) {
            return JsonError(labels('invalid_id'));
        }

        $detail = $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->where('partner_id', $this->userId)
            ->first();

        if (empty($detail)) {
            return JsonError(labels('data_not_found'));
        }

        $bookingModel = model(BookingHandymenModel::class);
        $activeBookings = $bookingModel->getActiveBookingAssignments($handymanId);
        $hasActiveBooking = !empty($activeBookings);

        $message = $hasActiveBooking
            ? labels('handyman_has_active_booking', 'This handyman is assigned to a booking that is still in progress and cannot be deleted until it is completed or cancelled.')
            : labels('data_retrieved_successfully', 'Data retrieved successfully');

        return JsonSuccess($message, null, [
            'active_booking' => $hasActiveBooking,
            'bookings' => $this->formatActiveBookings($activeBookings),
        ]);
    }

    /**
     * Shapes raw booking_handymen/orders rows for the "cannot delete/deactivate" modal.
     */
    private function formatActiveBookings(array $rows): array
    {
        return array_map(static function (array $row): array {
            return [
                'order_id' => (int) $row['order_id'],
                'customer_name' => $row['customer_name'] ?? '-',
                'handyman_status' => $row['handyman_status'],
                'order_status' => $row['order_status'],
                'date_of_service' => $row['date_of_service'],
                'starting_time' => $row['starting_time'],
                'ending_time' => $row['ending_time'],
            ];
        }, $rows);
    }

    // public function toggleAvailability()
    // {
    //     if (!$this->isLoggedIn) {
    //         return JsonError(labels('unauthorized'));
    //     }

    //     $handymanId = (int) ($this->request->getPost('id') ?: 0);
    //     if ($handymanId <= 0) {
    //         return JsonError(labels('invalid_id'));
    //     }

    //     $detail = $this->handymanDetailsModel
    //         ->where('handyman_id', $handymanId)
    //         ->where('partner_id', $this->userId)
    //         ->first();

    //     if (empty($detail)) {
    //         return JsonError(labels('data_not_found'));
    //     }

    //     $newAvailable = ((int) $detail['is_available'] === 1) ? 0 : 1;

    //     $this->handymanDetailsModel
    //         ->where('handyman_id', $handymanId)
    //         ->set(['is_available' => $newAvailable])
    //         ->update();

    //     return JsonSuccess($newAvailable === 1 ? labels('marked_available') : labels('marked_unavailable'));
    // }

    private function getOwnedHandyman(int $handymanId): ?array
    {
        if ($handymanId <= 0) {
            return null;
        }

        return $this->handymanDetailsModel
            ->where('handyman_id', $handymanId)
            ->where('partner_id', $this->userId)
            ->first();
    }

    private function getCustomFields(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $toBool = static function ($v): bool {
            if (is_bool($v)) {
                return $v;
            }
            if (is_int($v)) {
                return $v === 1;
            }
            $s = strtolower(trim((string) $v));
            return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
        };

        $rows = $db->table('custom_fields')
            ->select(['id', 'field_label', 'field_type', 'file_config', 'required', 'visible', 'sort_order'])
            ->where('field_group', 'handyman_details')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $fields = [];
        foreach ($rows as $row) {
            if (!$toBool($row['visible'] ?? 0)) {
                continue;
            }
            $fileConfigRaw = (string) ($row['file_config'] ?? '');
            $fields[] = [
                'id' => (int) $row['id'],
                'field_label' => (string) $row['field_label'],
                'field_type' => strtolower(trim((string) $row['field_type'])),
                'file_config' => $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [],
                'required' => $toBool($row['required'] ?? 0) ? 1 : 0,
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $fields;
    }
}
