<?php

namespace App\Controllers\Admin;

use App\Models\Orders_model;
use App\Models\Transaction_model;
use App\Models\Users_model;
use App\Models\HandymanDetailsModel;
use App\Models\BookingHandymenModel;
use App\Services\utility\FileService;

class Orders extends Admin
{
    public $orders, $creator_id, $transaction, $user_model;
    protected $superadmin;
    protected $db;
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->orders = new Orders_model();
        $this->creator_id = $this->userId;
        $this->transaction = new Transaction_model();
        $this->user_model = new Users_model();
        $this->fileService = new FileService();
        helper(['form', 'url', 'upload', 'ResponceServices']);
        $this->superadmin = $this->session->get('email');
        $this->db = \Config\Database::connect();
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('booking', 'Booking') . ' | ' . labels('admin_panel', 'Admin Panel'), 'orders');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        $partner_data = $this->db->table('users u')
            ->select('u.id,u.username,pd.company_name,pd.number_of_members')
            ->join('partner_details pd', 'pd.partner_id = u.id')
            ->where('is_approved', '1')
            ->get()->getResultArray();
        $this->data['partner_name'] = $partner_data;
        return view('backend/admin/template', $this->data);
    }
    public function list()
    {
        $orders_model = new Orders_model();
        return $orders_model->listForBookingsTable();
    }

    // FullCalendar event source for the Bookings Calendar tab.
    // Returns one event per order keyed on date_of_service; title is "INV-{id}".
    public function orders_calendar()
    {
        try {
            $response = [];

            $isValidDate = static function (string $value): bool {
                if ($value === '')
                    return false;
                $ts = strtotime($value);
                return $ts !== false && date('Y-m-d', $ts) === $value;
            };
            $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
            $dateTo = trim((string) ($_GET['date_to'] ?? ''));
            if (!$isValidDate($dateFrom))
                $dateFrom = '';
            if (!$isValidDate($dateTo))
                $dateTo = '';
            if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $search = trim((string) ($_GET['search'] ?? ''));
            $statusFilter = trim((string) ($_GET['order_status_filter'] ?? ''));
            $providerFilter = (int) ($_GET['order_provider_filter'] ?? 0);

            $builder = $this->db->table('orders o')
                ->select("o.id, o.date_of_service, o.starting_time, o.ending_time,
                          o.status, o.final_total, o.partner_id, o.user_id,
                          u.username AS customer_name,
                          p.username AS partner_name,
                          pd.company_name", false)
                ->join('users u', 'u.id = o.user_id')
                ->join('users p', 'p.id = o.partner_id')
                ->join('partner_details pd', 'pd.partner_id = o.partner_id')
                ->join('order_services os', 'os.order_id = o.id')
                ->groupStart()
                ->where('o.parent_id', null)
                ->orWhere('o.parent_id', 0)
                ->orWhere('o.parent_id', '')
                ->groupEnd();

            if ($dateFrom !== '')
                $builder->where('o.date_of_service >=', $dateFrom);
            if ($dateTo !== '')
                $builder->where('o.date_of_service <=', $dateTo);
            if ($statusFilter !== '')
                $builder->where('o.status', $statusFilter);
            if ($providerFilter > 0)
                $builder->where('o.partner_id', $providerFilter);

            if ($search !== '') {
                $escapedSearch = $this->db->escapeLikeString($search);
                $builder->groupStart()
                    ->like('o.id', $escapedSearch)
                    ->orLike('u.username', $escapedSearch)
                    ->orLike('p.username', $escapedSearch)
                    ->orLike('pd.company_name', $escapedSearch)
                    ->orLike('o.status', $escapedSearch)
                    ->groupEnd();
            }

            $builder->where('o.date_of_service IS NOT NULL', null, false);

            $rows = $builder->groupBy('o.id')
                ->orderBy('o.date_of_service', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $response[] = [
                    'title' => 'INV-' . $row['id'],
                    'start' => $row['date_of_service'],
                    'allDay' => true,
                    'url' => base_url('admin/orders/veiw_orders/' . $row['id']),
                    'extendedProps' => [
                        'order_id' => (int) $row['id'],
                        'status' => (string) $row['status'],
                        'customer_name' => (string) ($row['customer_name'] ?? ''),
                        'partner_name' => (string) ($row['partner_name'] ?? ''),
                        'company_name' => (string) ($row['company_name'] ?? ''),
                        'starting_time' => (string) ($row['starting_time'] ?? ''),
                        'ending_time' => (string) ($row['ending_time'] ?? ''),
                        'final_total' => (string) ($row['final_total'] ?? ''),
                    ],
                ];
            }

            // Wrap in object so csrf_response after-filter doesn't convert
            // the indexed array into {0:..., 1:..., csrfName, csrfHash}.
            return $this->response->setJSON(['events' => $response]);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/Admin/Orders.php - orders_calendar(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setJSON(['events' => []]);
        }
    }

    public function view_orders()
    {
        try {
            $uri = service('uri');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $order_id = $uri->getSegments()[3];
            $orders = fetch_details('orders', ['id' => $order_id]);
            $sub_orders = fetch_details('orders', ['parent_id' => $order_id]);
            setPageInfo($this->data, $order_id . ' ' . labels('ID - Booking Details', 'ID - Booking Details') . ' | ' . labels('admin_panel', 'Admin Panel'), 'order_details');
            if (isset($orders) && empty($orders)) {
                return redirect('admin/orders');
            } else {
                $orders = $orders[0];
            }
            $partner_id = $orders['partner_id'];
            $user_id = $orders['user_id'];
            $partner = fetch_details('partner_details', ['partner_id' => $partner_id])[0] ?? [];
            $partner_personal_data = fetch_details('users', ['id' => $partner_id])[0] ?? [];
            $partner_personal_data['image'] = $this->fileService->url($partner_personal_data['image'] ?? null, 'profile');

            $customer = fetch_details('users', ['id' => $user_id])[0] ?? [];
            $customer['image'] = $this->fileService->url($customer['image'] ?? null, 'profile');

            $payment = fetch_details('transactions', ['order_id' => $order_id]);
            $order_services = fetch_details('order_services', ['order_id' => $order_id, 'status!=' => 'cancelled'])[0] ?? [];
            $service_id = $order_services['service_id'] ?? 0;
            $service = fetch_details('services', ['id' => $service_id]);
            if (!empty($service)) {
                $service = $service[0];
            } else {
                $service = [];
            }
            $where = "o.id = $order_id";
            $order_details = $this->orders->list(true, '', 10, 0, '', '', $where);

            $where = "u.id = $user_id";
            $currency = get_settings('general_settings', true);
            $this->data['currency'] = $currency['currency'];
            $this->data['order'] = $orders;
            $this->data['order_services'] = $order_services;
            if (empty($order_details['data'][0])) {
                return redirect('admin/orders');
            }
            $this->data['order_details'] = $order_details['data'][0];

            // Totals from stored order_services (tax_amount, sub_total) so view only displays, no logic
            $od = $order_details['data'][0];
            $order_tax_amount = 0;
            if (!empty($od['services']) && is_array($od['services'])) {
                foreach ($od['services'] as $s) {
                    $order_tax_amount += floatval($s['tax_amount'] ?? 0) * floatval($s['quantity'] ?? 0);
                }
            }
            $order_total = floatval(str_replace(',', '', $od['total'] ?? 0));
            $order_subtotal = $order_total - $order_tax_amount;
            $this->data['order_subtotal'] = format_amount($order_subtotal);
            $this->data['order_tax_amount'] = format_amount($order_tax_amount);
            $this->data['order_tax_type'] = !empty($od['services']) ? ($od['services'][0]['tax_type'] ?? '') : '';
            $this->data['order_final_total'] = format_amount(floatval(str_replace(',', '', $od['final_total'] ?? 0)));

            $this->data['personal_data'] = $partner_personal_data;
            $this->data['customer'] = $customer;
            $this->data['payment'] = $payment;
            $this->data['service'] = $service;
            $this->data['sub_order'] = $sub_orders;
            $this->data['cancel_reasons'] = get_cancel_reasons_for_panel();

            $cancel_info = ['reason' => null, 'additional_info' => null, 'cancelled_by' => null];
            if (!empty($orders['cancel_reason_id'])) {
                foreach ($this->data['cancel_reasons'] as $cr) {
                    if ((int) $cr['id'] === (int) $orders['cancel_reason_id']) {
                        $cancel_info['reason'] = $cr['reason'];
                        break;
                    }
                }
            }
            $cancel_info['additional_info'] = $orders['cancel_additional_info'] ?? null;
            $cancel_info['cancelled_by'] = $orders['cancelled_by'] ?? null;
            $this->data['cancel_info'] = $cancel_info;

            // Handyman assignment data
            $handymanModel = new HandymanDetailsModel();

            $assignedRows = $this->db->table('booking_handymen oh')
                ->select('oh.handyman_id AS id, u.username, u.image, oh.status AS handyman_status, oh.is_lead')
                ->join('users u', 'u.id = oh.handyman_id')
                ->where('oh.order_id', $order_id)
                ->get()->getResultArray();
            $this->data['assigned_handymen'] = $assignedRows;
            $this->data['assigned_handyman_ids'] = array_column($assignedRows, 'id');

            $allAvailable = $handymanModel->getForAssignment((int) $partner_id);
            $assignedIdSet = array_flip($this->data['assigned_handyman_ids']);

            $slotSettingsModel = new \App\Models\ProviderSlotSettings_model();
            $slotSettings = $slotSettingsModel->findByPartner((int) $partner_id) ?? [];
            $bufferBefore = (int) ($slotSettings['buffer_before'] ?? 0);
            $bufferAfter = (int) ($slotSettings['buffer_after'] ?? 0);
            $orderConflicts = $handymanModel->getConflictsForOrder(
                (int) $partner_id,
                (int) $order_id,
                $orders['date_of_service'] ?? '',
                $orders['starting_time'] ?? '',
                $orders['ending_time'] ?? '',
                $bufferBefore,
                $bufferAfter
            );
            $conflictMap = array_column($orderConflicts, 'current_order_id', 'id');

            $mergedHandymen = [];
            foreach ($allAvailable as &$h) {
                $h['image_url'] = $this->fileService->url($h['image'], 'profile');
                $h['conflicting_order_id'] = isset($conflictMap[$h['id']]) ? (int) $conflictMap[$h['id']] : null;
                if (isset($assignedIdSet[$h['id']]))
                    continue;
                $mergedHandymen[] = $h;
            }
            unset($h);
            usort($mergedHandymen, fn($a, $b) => ($a['conflicting_order_id'] !== null) <=> ($b['conflicting_order_id'] !== null));
            $this->data['handymen'] = $mergedHandymen;

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'app/Controllers/admin/Orders.php - view_orders(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function view_user()
    {
        try {
            $uri = service('uri');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $order_id = $uri->getSegments()[3];
            $orders = fetch_details('orders', ['id' => $order_id]);
            if (isset($orders) && empty($orders)) {
                return redirect('admin/orders');
            } else {
                $orders = $orders[0];
            }
            $user_id = $orders['user_id'];
            $where['u.id'] = $user_id;
            $users = fetch_details('users', ['id' => $user_id]);
            $user_details = $this->user_model->get_user($user_id, '');
            if (!empty($user_details)) {
                $user_details[0]['image'] = $this->fileService->url($user_details[0]['image'] ?? null, 'profile');
            }
            return json_encode($user_details);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - view_user(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function view_payment_details()
    {
        try {
            $uri = service('uri');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $order_id = $uri->getSegments()[3];
            $orders = fetch_details('orders', ['id' => $order_id]);
            if (isset($orders) && empty($orders)) {
                return redirect('admin/orders');
            } else {
                $orders = $orders[0];
            }
            $order_id = $orders['id'];
            $db = \Config\Database::connect();
            $where['t.order_id'] = $order_id;
            $payment_details = $this->transaction->list_transactions(false, '', 10, 0, 't.id', 'desc', $where);
            return $payment_details;
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - view_payment_details(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function customer_details($user_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $order_details = fetch_details('orders', ['id' => $order_id], ['user_id', 'city', 'address_id'])[0];
            $user_id = $order_details['user_id'];
            $address_id = $order_details['address_id'];
            $db = \Config\Database::connect();
            $builder = $db->table('orders o');
            $count = $builder->select('COUNT(o.id) as total')
                ->join('users u', "u.id = $user_id")
                ->join('addresses a', "a.id =  $address_id")
                ->where('o.id', $order_id)->get()->getResultArray();
            $total = $count[0]['total'];
            $tempRow = array();
            $data = $builder->select('u.username, u.email, u.phone, a.type, a.address,a.city')
                ->join('users u', "u.id = $user_id")
                ->join('addresses a', "a.id =  $address_id")
                ->where('o.id', $order_id)->get()->getResultArray();
            $rows = [];
            foreach ($data as $row) {
                $tempRow['name'] = $row['username'];
                $tempRow['email'] = ($row['email'] != '') ? $row['email'] : '-';
                $tempRow['phone'] = $row['phone'];
                $tempRow['city_name'] = $row['city'];
                $tempRow['type'] = $row['type'];
                $tempRow['address'] = $row['address'];
                $rows[] = $tempRow;
            }
            $bulkData['total'] = $total;
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - customer_details(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function payment_details($order_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $db = \Config\Database::connect();
            $builder = $db->table('transactions t');
            $count = $builder->select('COUNT(t.id) as total')
                ->where('t.order_id', $order_id)->get()->getResultArray();
            $total = $count[0]['total'];
            $tempRow = array();
            $data = $builder->select('t.*')
                ->where('t.order_id', $order_id)->get()->getResultArray();
            $rows = [];
            foreach ($data as $row) {
                $tempRow['transaction_type'] = $row['transaction_type'];
                $tempRow['payment_method'] = $row['type'];
                $tempRow['txn_id'] = $row['txn_id'];
                $tempRow['amount'] = $row['amount'];
                $tempRow['status'] = $row['status'];
                $tempRow['currency_code'] = $row['currency_code'];
                $tempRow['message'] = $row['message'];
                $tempRow['transaction_date'] = $row['transaction_date'];
                $rows[] = $tempRow;
            }
            $bulkData['total'] = $total;
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - payment_details(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_details($order_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $partner_id = fetch_details('orders', ['id' => $order_id], ['partner_id'])[0];
            $db = \Config\Database::connect();
            $builder = $db->table('partner_details pd');
            $count = $builder->select('COUNT(pd.id) as total')
                ->where('pd.partner_id', $partner_id)->get()->getResultArray();
            $total = $count[0]['total'];
            $tempRow = array();
            $data = $builder->select('pd.*, u.username, u.email, u.phone, u.image')
                ->join('users u', 'u.id = pd.partner_id')
                ->where('pd.partner_id', $partner_id)->get()->getResultArray();
            $rows = [];
            foreach ($data as $row) {
                $imageUrl = $this->fileService->url($row['image'], 'profile');
                $profile = '<a  href="' . $imageUrl . '" data-lightbox="image-1"><img height="80px" class="rounded-circle" src="' . $imageUrl . '" alt=""></a>';
                $tempRow['user_image'] = $profile;
                $tempRow['name'] = $row['username'];
                $tempRow['email'] = $row['email'];
                $tempRow['phone'] = $row['phone'];
                $tempRow['company_name'] = $row['company_name'];
                $tempRow['about'] = $row['about'];
                $rows[] = $tempRow;
            }
            $bulkData['total'] = $total;
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - partner_details(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_orders()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn) {
                return redirect('admin/login');
            }
            $order_id = $this->request->getPost('id');
            $db = \Config\Database::connect();

            $old_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($old_data)) {
                $proofs = ['work_started_proof', 'work_completed_proof'];
                foreach ($proofs as $proof_field) {
                    if (!empty($old_data[0][$proof_field])) {
                        $files = json_decode($old_data[0][$proof_field], true);
                        if (is_array($files)) {
                            foreach ($files as $file) {
                                $this->fileService->delete('provider_work_evidence', $file);
                            }
                        }
                    }
                }
            }

            $builder = $db->table('orders')->where('id', $order_id)
                ->orWhere('parent_id', $order_id)
                ->delete();
            $builder = $db->table('order_services')->delete(['order_id' => $order_id]);
            if ($builder) {
                return successResponse("order deleted successfully", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse("order does not exist!", true, [], [], 200, csrf_token(), csrf_hash());
            }
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - delete_orders(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function view_details()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('view_order', 'View Booking') . ' | ' . labels('admin_panel', 'Admin Panel'), 'order_details');
        return view('backend/admin/template', $this->data);
    }
    public function invoice()
    {

        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $db = \Config\Database::connect();
            setPageInfo($this->data, 'View Invoice | ' . labels('admin_panel', 'Admin Panel'), 'invoice');
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $orders = fetch_details('orders', ['id' => $order_id]);
            if (isset($orders) && empty($orders)) {
                return redirect('admin/orders');
            }
            $order_details = $this->orders->invoice($order_id)['order'];
            $partner_id = $order_details['partner_id'];
            $partner_details = $db
                ->table('partner_details pd')
                ->select('pd.company_name,pd.address, u.*')
                ->join('users u', 'u.id = pd.partner_id')
                ->where('partner_id', $partner_id)->get()->getResultArray();
            $user_id = $order_details['user_id'];
            $user_details = $db
                ->table('users u')
                ->select('u.*')
                ->where('u.id', $user_id)
                ->get()->getResultArray();

            if (!empty($partner_details)) {
                $partner_details[0]['image'] = $this->fileService->url($partner_details[0]['image'] ?? null, 'profile');
            }
            if (!empty($user_details)) {
                $user_details[0]['image'] = $this->fileService->url($user_details[0]['image'] ?? null, 'profile');
            }

            $data = get_settings('general_settings', true);
            if (!empty($data['logo'])) {
                $data['logo'] = $this->fileService->url($data['logo'], 'site');
            }

            $orderDate = !empty($order_details['created_at']) ? strtotime($order_details['created_at']) : time();
            $year = (int) date('Y', $orderDate);
            $month = (int) date('n', $orderDate);
            $fyStart = $month >= 4 ? date('y', strtotime($year . '-01-01')) : date('y', strtotime(($year - 1) . '-01-01'));
            $fyEnd = $month >= 4 ? date('y', strtotime(($year + 1) . '-01-01')) : date('y', strtotime($year . '-01-01'));
            $order_details['invoice_no'] = 'ATPL/E-' . str_pad((string) $order_id, 3, '0', STR_PAD_LEFT) . '/' . $fyStart . '-' . $fyEnd;

            $additional_charges = [];
            if (!empty($order_details['additional_charges'])) {
                $decoded_charges = is_array($order_details['additional_charges'])
                    ? $order_details['additional_charges']
                    : json_decode($order_details['additional_charges'], true);
                $additional_charges = is_array($decoded_charges) ? $decoded_charges : [];
            }
            $services = is_array($order_details['services'] ?? null) ? $order_details['services'] : [];
            $service_taxable_amount = 0;
            $service_tax_amount = 0;
            $default_tax_percentage = (float) ($services[0]['tax_percentage'] ?? 0);
            foreach ($services as $service) {
                $unit_price = (float) (($service['discount_price'] ?? 0) > 0 ? $service['discount_price'] : ($service['price'] ?? 0));
                $quantity = (int) ($service['quantity'] ?? 1);
                $line_net_amount = $unit_price * $quantity;
                $line_tax_amount = calculate_tax_amount(
                    $line_net_amount,
                    (float) ($service['tax_percentage'] ?? 0),
                    $service['tax_type'] ?? 'excluded'
                );
                $service_taxable_amount += ($service['tax_type'] ?? 'excluded') === 'included'
                    ? $line_net_amount - $line_tax_amount
                    : $line_net_amount;
                $service_tax_amount += $line_tax_amount;
            }
            $additional_charge_tax = array_sum(array_map(
                static function ($charge) use ($default_tax_percentage) {
                    $charge_amount = (float) ($charge['charge'] ?? 0);
                    return (float) ($charge['tax_amount'] ?? calculate_tax_amount(
                        $charge_amount,
                        (float) ($charge['tax_percentage'] ?? $default_tax_percentage),
                        $charge['tax_type'] ?? 'excluded'
                    ));
                },
                $additional_charges
            ));
            $visiting_amount = (float) ($order_details['visiting_charges'] ?? 0);
            $visiting_tax_amount = $visiting_amount > 0
                ? calculate_tax_amount($visiting_amount, $default_tax_percentage, 'excluded')
                : 0;
            $order_details['total'] = number_format($service_taxable_amount, 2, '.', '');
            $order_details['tax'] = number_format($service_tax_amount + $visiting_tax_amount + $additional_charge_tax, 2, '.', '');
            $order_details['additional_charge_tax'] = number_format($additional_charge_tax, 2, '.', '');

            $this->data['currency'] = $data['currency'];
            $this->data['data'] = $data;
            $this->data['order'] = $order_details;
            $this->data['additional_charges'] = $additional_charges;
            $this->data['additional_charge_tax'] = $additional_charge_tax;
            $this->data['partner_details'] = $partner_details[0];
            $this->data['user_details'] = $user_details[0];
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - invoice(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function invoice_table()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $orders = fetch_details('orders', ['id' => $order_id]);
            if (isset($orders) && empty($orders)) {
                return redirect('admin/orders');
            }
            $orders_model = new Orders_model();
            $data = get_settings('general_settings', true);
            $currency = $data['currency'];
            $tax = get_settings('system_tax_settings', true);
            $orders = $orders_model->invoice($order_id)['order'];
            $services = $orders['services'];
            $total = count($services);
            if (!empty($orders)) {
                $i = 0;
                $rows = [];

                // Recalculate totals from services using same logic as customer invoice (V1::invoice_download)
                $sum_net_amount = 0;
                $sum_tax_amount = 0;

                foreach ($services as $service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);
                    $currency_symbol = $currency;

                    // Unit price (discounted or original); line net = unit price * qty
                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;
                    $line_tax = calculate_tax_amount(
                        $line_net,
                        (float) ($service['tax_percentage'] ?? 0),
                        $service['tax_type'] ?? 'excluded'
                    );

                    $sum_net_amount += ($service['tax_type'] ?? 'excluded') === 'included'
                        ? $line_net - $line_tax
                        : $line_net;
                    $sum_tax_amount += $line_tax;

                    // Row display: net_amount = unit price; tax_amount = line tax; subtotal = line subtotal (match customer invoice)
                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency_symbol . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency_symbol . "0.00" : $currency_symbol . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency_symbol . number_format($unitPrice, 2, '.', ''),
                        'tax' => ($service['tax_percentage'] ?? '') . '%',
                        'tax_amount' => $currency_symbol . number_format($line_tax, 2, '.', ''),
                        'quantity' => ucwords((string) $qty),
                        'subtotal' => $currency_symbol . number_format($service['sub_total'], 2, '.', '')
                    ];
                    $i++;
                }

                $service_tax_percentage = (float) ($services[0]['tax_percentage'] ?? 0);
                $visiting_amount = (float) ($orders['visiting_charges'] ?? 0);
                $visiting_tax = $visiting_amount > 0
                    ? calculate_tax_amount($visiting_amount, $service_tax_percentage, 'excluded')
                    : 0;

                if ($visiting_amount > 0) {
                    $rows[] = [
                        'service_title' => labels('visiting_charges', 'Visiting Charge'),
                        'price' => $currency . number_format($visiting_amount, 2, '.', ''),
                        'discount' => $currency . '0.00',
                        'net_amount' => $currency . number_format($visiting_amount, 2, '.', ''),
                        'tax' => number_format($service_tax_percentage, 2, '.', '') . '%',
                        'tax_amount' => $currency . number_format($visiting_tax, 2, '.', ''),
                        'quantity' => '1',
                        'subtotal' => $currency . number_format($visiting_amount + $visiting_tax, 2, '.', '')
                    ];
                    $sum_tax_amount += $visiting_tax;
                }

                $additional_charges = !empty($orders['additional_charges'])
                    ? (is_array($orders['additional_charges']) ? $orders['additional_charges'] : json_decode($orders['additional_charges'], true))
                    : [];
                $additional_charges = is_array($additional_charges) ? $additional_charges : [];
                foreach ($additional_charges as $charge) {
                    $charge_amount = (float) ($charge['charge'] ?? 0);
                    $charge_tax_percentage = (float) ($charge['tax_percentage'] ?? $service_tax_percentage);
                    $charge_tax = (float) ($charge['tax_amount'] ?? calculate_tax_amount($charge_amount, $charge_tax_percentage, 'excluded'));
                    $rows[] = [
                        'service_title' => $charge['name'] ?? labels('additional_charge', 'Additional Charge'),
                        'price' => $currency . number_format($charge_amount, 2, '.', ''),
                        'discount' => $currency . '0.00',
                        'net_amount' => $currency . number_format($charge_amount, 2, '.', ''),
                        'tax' => number_format($charge_tax_percentage, 2, '.', '') . '%',
                        'tax_amount' => $currency . number_format($charge_tax, 2, '.', ''),
                        'quantity' => '1',
                        'subtotal' => $currency . number_format($charge_amount + $charge_tax, 2, '.', '')
                    ];
                    $sum_tax_amount += $charge_tax;
                }

                // Use recalculated totals (same as customer invoice), not DB order total
                $recalc_total = number_format($sum_net_amount, 2, '.', '');

                // Empty row for formatting
                $empty_row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "",
                    'subtotal' => "",
                ];
                // Total row: subtotal (including tax) minus tax = amount before tax (excluded tax)
                $row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark'>" . labels('total', 'Total') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $recalc_total . "</strong>",
                ];

                // Tax row: show total tax amount just below Total (for clarity on invoice)
                $tax_row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark'>" . labels('tax', 'Tax') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . number_format($sum_tax_amount, 2, '.', '') . "</strong>",
                ];

                // Visiting charges row
                if ($orders['visiting_charges'] != "0") {
                    $visiting_charges = [
                        'service_title' => "",
                        'price' => "",
                        'discount' => "",
                        'net_amount' => "",
                        'tax' => "",
                        'tax_amount' => "",
                        'quantity' => "<strong class='text-dark '>" . labels('visiting_charges', 'Visiting Charges') . "</strong>",
                        'subtotal' => "<strong class='text-dark '>" . $currency . $orders['visiting_charges'] . "</strong>",
                    ];
                }
                // Promo code discount row
                $promo_code_discount = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark '>" . labels('promocode_discount', 'Promo Code Discount') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $orders['promo_discount'] . "</strong>",
                ];
                // Final total row
                $payble_amount = $orders['final_total'] - $orders['promo_discount'];
                $final_total = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark '>" . labels('final_total', 'Final Total') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $payble_amount . "</strong>",
                ];
                // Summary totals are rendered beside the table in the invoice view.
                $array['total'] = $total;
                $array['rows'] = $rows;
                return json_encode($array);
            }
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - invoice_table(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function change_order_status()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $uri = service('uri');
            $order_id = $this->request->getPost('order_id');
            $status = $this->request->getPost('status');
            $otp = $this->request->getPost('otp');
            $uploadedFiles = $this->request->getFiles();
            $date = $this->request->getPost('rescheduled_date');
            $selected_time = $this->request->getPost('reschedule');
            $partner_id = fetch_details('orders', ['id' => $order_id], ['partner_id'])[0]['partner_id'];
            if ($status == "rescheduled" && $selected_time == "") {
                return ErrorResponse(labels('please_select_reschedule_timing', "Please select reschedule timing"), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $orders = fetch_details('orders', ['id' => $order_id]);
            $service_total_duration = 0;
            $service_duration = 0;
            foreach ($orders as $row) {
                $service_duration = ($row['duration']);
                $service_total_duration = $service_total_duration + $service_duration;
            }
            if ($status == "rescheduled") {
                // validate_order_status handles the lock-first + atomic update via SlotService now.
                $response = validate_order_status($order_id, $status, $date, $selected_time, null, null, null, $this->userId, null, 'admin');
                return json_encode($response);
            } else {
                if ($status == "completed") {
                    // Pass the actor (admin) user_id for correct notification context.
                    $response = validate_order_status($order_id, $status, '', '', $otp, null, null, $this->userId, null, 'admin');
                } elseif ($status == "started") {
                    // Pass the actor (admin) user_id for correct notification context.
                    $response = validate_order_status($order_id, $status, '', '', '', isset($uploadedFiles) ? $uploadedFiles : "", null, $this->userId, null, 'admin');
                } elseif ($status == "booking_ended") {
                    $additionalCharge = $this->request->getPost('booking_ended_additional_charges') ?? '';
                    // Pass the actor (admin) user_id for correct notification context.
                    $response = validate_order_status($order_id, $status, '', '', '', isset($uploadedFiles) ? $uploadedFiles : "", $additionalCharge, $this->userId, null, 'admin');
                } elseif ($status == "cancelled") {
                    $cancel_reason_id = $this->request->getPost('cancel_reason_id');
                    $additional_info = $this->request->getPost('additional_info');
                    $cancel_validation = validate_cancel_reason($cancel_reason_id, $additional_info);
                    if ($cancel_validation['error']) {
                        return ErrorResponse($cancel_validation['message'], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $response = validate_order_status($order_id, $status, '', '', null, null, null, $this->userId, null, 'admin');
                    if (!$response['error']) {
                        update_details([
                            'cancel_reason_id' => (int) $cancel_reason_id,
                            'cancel_additional_info' => $cancel_validation['additional_info'],
                            'cancelled_by' => 'admin',
                        ], ['id' => $order_id], 'orders');
                    }
                } else {
                    // Pass the actor (admin) user_id for correct notification context.
                    $response = validate_order_status($order_id, $status, '', '', null, null, null, $this->userId, null, 'admin');
                }
                if (in_array($status, ['confirmed', 'started'])) {
                    $this->assignInlineHandymen($order_id, $response);
                }
                // Admin-driven status change: mirror it onto assigned handymen so the
                // handyman app doesn't show a stale status (booking_handymen.status).
                if (
                    is_array($response) && isset($response['error']) && $response['error'] == false
                    && in_array($status, ['on_the_way', 'arrived', 'started', 'booking_ended', 'completed'], true)
                ) {
                    (new BookingHandymenModel())->syncStatusForOrder((int) $order_id, $status);
                }
                return json_encode($response);
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'app/Controllers/admin/Orders.php - change_order_status(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function upload_file()
    {
        try {
            $order_id = $this->request->getPost('order_id');
            $status = $this->request->getPost('status');
            $imagefile = $this->request->getFiles();
            $is_completed = $this->request->getPost('is_completed');
            $db = \Config\Database::connect();
            $builder = $db->table('orders');
            $builder->select('status,payment_method,user_id,otp')->where('id', $order_id);
            $active_status1 = $builder->get()->getResultArray();
            $active_status = (isset($active_status1[0]['status'])) ? $active_status1[0]['status'] : "";
            if ($active_status == $status) {
                return ErrorResponse("You cant update the same status again", true, [], [], 200, csrf_token(), csrf_hash());
            }
            if ($active_status == 'cancelled' || $active_status == 'completed') {
                return ErrorResponse("You cant update status once item cancelled or completed", true, [], [], 200, csrf_token(), csrf_hash());
            }
            $work_started_images = [];
            if (isset($imagefile['documents'])) {
                foreach ($imagefile['documents'] as $key => $img) {
                    if ($img->isValid() && !$img->hasMoved()) {
                        $result = $this->fileService->upload($img, 'provider_work_evidence');
                        if (!$result['error']) {
                            $work_started_images[$key] = $result['path'];
                        }
                    } else {
                        echo labels('file_is_not_valid', 'file is not valid');
                    }
                }
            }
            if (isset($is_completed)) {
                $update = update_details(
                    [
                        'work_completed_proof' => json_encode((object) $work_started_images),
                    ],
                    ['id' => $order_id],
                    'orders',
                    false
                );
            } else {
                $update = update_details(
                    [
                        'work_started_proof' => json_encode((object) $work_started_images),
                    ],
                    ['id' => $order_id],
                    'orders',
                    false
                );
            }
            if ($update) {
                return successResponse("Success", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse("Failed", true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Exception $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - upload_file(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function view_ordered_services()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('booking', 'Booking') . ' | ' . labels('admin_panel', 'Admin Panel'), 'view_ordered_services');
        return view('backend/admin/template', $this->data);
    }
    public function view_ordered_services_list()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'desc';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->orders = new Orders_model();
            $data = $this->orders->ordered_services_list(false, $search, $limit, $offset, $sort, $order);
            return $data;
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - view_ordered_services_list(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function cancel_order_service()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $id = $this->request->getPost('id');
            $service_id = $this->request->getPost('service_id');
            $service_details = fetch_details('services', ['id' => $service_id]);
            $order_service_data = fetch_details('order_services', ['id' => $id]);
            $order_id = $order_service_data[0]['order_id'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($service_details)) {
                $date_of_service = $order_service_data[0]['created_at'];
                $starting_time = (!empty($order_data)) ? $order_data[0]['starting_time'] : '';
                $cancelable_before = $service_details[0]['cancelable_till'];
                $change = update_details(['status' => "cancelled"], ['id' => $id], 'order_services');
                if ($change) {
                    $sub_total = $order_service_data[0]['sub_total'];
                    $amount = floatval($order_data[0]['total']) - floatval($order_service_data[0]['sub_total']);
                    $final_total = $order_data[0]['final_total'] - floatval($order_service_data[0]['sub_total']);
                    $promo_code_discount = ($order_data[0]['promo_discount'] != '' || $order_data[0]['promo_discount'] > 0) ? $order_data[0]['promo_discount'] : '';
                    $customer_id = $order_data[0]['user_id'];
                    if ($promo_code_discount != '' && $promo_code_discount > 0) {
                        $discountable_amount = ($final_total * $promo_code_discount) / 100;
                        $final_total = $final_total - $discountable_amount;
                    }
                    $change = update_details(['total' => $amount, 'final_total' => $final_total], ['id' => $order_id], 'orders');
                    if ($change) {
                        // Get current order status before cancellation (needed for notifications)
                        $active_status = !empty($order_data) && !empty($order_data[0]['status']) ? $order_data[0]['status'] : '';

                        // Process refund for the cancelled service
                        $refund_process = process_service_refund($order_id, $service_id, 'cancelled', $customer_id, $sub_total);

                        // Send email notifications to customer when admin cancels a service
                        // This ensures customers are notified via email, SMS, and FCM when admin cancels a service in their booking
                        $trans = new \Config\ApiResponseAndNotificationStrings();
                        $translated_status = $trans->cancelled;
                        $languageCode = get_default_language();
                        $admin_user_id = $this->userId ?? null;

                        send_booking_status_notifications($order_id, 'cancelled', $translated_status, $active_status, $languageCode, $admin_user_id);

                        return $this->response->setJSON($refund_process);
                    } else {
                        return ErrorResponse("could not change order status", true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            }
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - view_ordered_services_list(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function assign_handyman()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorised', 'Unauthorised')]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $handyman_ids = $this->request->getPost('handyman_ids');

            $order = $this->db->table('orders')->select('id, status, partner_id, date_of_service, starting_time, ending_time')->where('id', $order_id)->get()->getRowArray();
            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (!in_array($order['status'] ?? '', ['confirmed', 'started', 'rescheduled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $partner_id = (int) $order['partner_id'];
            $handymanModel = new HandymanDetailsModel();
            $handymen = $handymanModel->getForAssignment($partner_id);
            $validIds = array_column($handymen, 'id');

            if (empty($handyman_ids) || !is_array($handyman_ids)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('select_handyman', 'Select Handyman'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $slotSettingsModel = new \App\Models\ProviderSlotSettings_model();
            $slotSettings = $slotSettingsModel->findByPartner($partner_id) ?? [];
            $bufferBefore = (int) ($slotSettings['buffer_before'] ?? 0);
            $bufferAfter = (int) ($slotSettings['buffer_after'] ?? 0);
            $conflicts = $handymanModel->getConflictsForOrder(
                $partner_id,
                $order_id,
                $order['date_of_service'] ?? '',
                $order['starting_time'] ?? '',
                $order['ending_time'] ?? '',
                $bufferBefore,
                $bufferAfter
            );
            $conflictIds = array_flip(array_column($conflicts, 'id'));

            $bookingHandymenModel = new BookingHandymenModel();
            $existingRows = $bookingHandymenModel->select('handyman_id, is_lead')->where('order_id', $order_id)->findAll();
            $existingIds = array_column($existingRows, 'handyman_id');
            $hasLeadAlready = !empty(array_filter($existingRows, fn($r) => (int) $r['is_lead'] === 1));

            $toInsert = [];
            $now = date('Y-m-d H:i:s');
            foreach ($handyman_ids as $hid) {
                $hid = (int) $hid;
                if (!in_array($hid, $validIds)) {
                    return $this->response->setJSON(['error' => true, 'message' => labels('invalid_handyman', 'Invalid handyman'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
                }
                if (isset($conflictIds[$hid])) {
                    return $this->response->setJSON(['error' => true, 'message' => labels('handyman_has_conflicting_booking', 'Handyman has a conflicting booking at that date and time'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
                }
                if (!in_array($hid, $existingIds)) {
                    // $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
                    $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'status' => 'accepted', 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
                }
            }

            if (!empty($toInsert) && !$hasLeadAlready) {
                $toInsert[0]['is_lead'] = 1;
            }

            if (!empty($toInsert)) {
                $bookingHandymenModel->insertBatch($toInsert);
                notify_handyman_assignment_change($order_id, array_column($toInsert, 'handyman_id'), 'booking_assigned', get_current_language());
            }

            $leadIds = array_column(array_filter($toInsert, fn($r) => (int) $r['is_lead'] === 1), 'handyman_id');

            $assignedNames = [];
            foreach ($handymen as $h) {
                if (in_array((int) $h['id'], array_column($toInsert, 'handyman_id'))) {
                    $assignedNames[] = ['id' => (int) $h['id'], 'username' => $h['username'], 'is_lead' => in_array((int) $h['id'], $leadIds) ? 1 : 0];
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('handyman_assigned_successfully', 'Handyman assigned successfully'),
                'assigned' => $assignedNames,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/Orders.php - assign_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        }
    }

    public function unassign_handyman()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorised', 'Unauthorised')]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $handyman_id = (int) $this->request->getPost('handyman_id');

            $order = $this->db->table('orders')->select('id, status')->where('id', $order_id)->get()->getRowArray();
            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (!in_array($order['status'] ?? '', ['confirmed', 'rescheduled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $bookingHandymenModel = new BookingHandymenModel();
            $bookingHandyman = $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $handyman_id)->first();

            if (empty($bookingHandyman)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_not_found', 'Handyman not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (!in_array($bookingHandyman['status'] ?? '', ['assigned', 'accepted'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_cannot_be_unassigned_in_current_status', 'Handyman cannot be unassigned in their current status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $wasLead = (int) ($bookingHandyman['is_lead'] ?? 0) === 1;

            $this->db->transStart();

            $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $handyman_id)->delete();

            if ($wasLead) {
                $remaining = $bookingHandymenModel->select('handyman_id')->where('order_id', $order_id)->first();
                if (!empty($remaining)) {
                    $bookingHandymenModel->where('order_id', $order_id)->where('handyman_id', $remaining['handyman_id'])->set(['is_lead' => 1])->update();
                }
            }

            $this->db->transComplete();
            if (!$this->db->transStatus()) {
                return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            notify_handyman_assignment_change($order_id, [$handyman_id], 'booking_unassigned', get_current_language());

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('handyman_unassigned_successfully', 'Handyman unassigned successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/Orders.php - unassign_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        }
    }

    public function set_lead_handyman()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorised', 'Unauthorised')]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $handyman_id = (int) $this->request->getPost('handyman_id');

            $order = $this->db->table('orders')->select('id, status')->where('id', $order_id)->get()->getRowArray();
            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (in_array($order['status'] ?? '', ['on_the_way', 'arrived', 'started', 'completed', 'cancelled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $row = $this->db->table('booking_handymen')->where('order_id', $order_id)->where('handyman_id', $handyman_id)->get()->getRowArray();
            if (empty($row)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_not_found', 'Handyman not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $this->db->transStart();
            $this->db->table('booking_handymen')->where('order_id', $order_id)->set(['is_lead' => 0])->update();
            $this->db->table('booking_handymen')->where('order_id', $order_id)->where('handyman_id', $handyman_id)->set(['is_lead' => 1])->update();
            $this->db->transComplete();
            if (!$this->db->transStatus()) {
                return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('lead_handyman_set_successfully', 'Lead handyman set successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/Orders.php - set_lead_handyman()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        }
    }

    private function assignInlineHandymen(int $order_id, array &$response): void
    {
        if (!empty($response['error']))
            return;
        $handymanIds = $this->request->getPost('handyman_ids');
        if (empty($handymanIds) || !is_array($handymanIds))
            return;

        $order = $this->db->table('orders')->select('partner_id')->where('id', $order_id)->get()->getRowArray();
        if (empty($order))
            return;

        $handymanModel = new HandymanDetailsModel();
        $validIds = array_column($handymanModel->getForAssignment((int) $order['partner_id']), 'id');
        $existingRows = $this->db->table('booking_handymen')->select('handyman_id, is_lead')->where('order_id', $order_id)->get()->getResultArray();
        $existingIds = array_column($existingRows, 'handyman_id');
        $hasLeadAlready = !empty(array_filter($existingRows, fn($r) => (int) $r['is_lead'] === 1));

        $toInsert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($handymanIds as $hid) {
            $hid = (int) $hid;
            if (in_array($hid, $validIds) && !in_array($hid, $existingIds)) {
                // $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
                $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'status' => 'accepted', 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
            }
        }
        if (!empty($toInsert) && !$hasLeadAlready) {
            $toInsert[0]['is_lead'] = 1;
        }
        if (!empty($toInsert)) {
            $this->db->table('booking_handymen')->insertBatch($toInsert);
        }
    }

    public function live_tracking($order_id = 0)
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $order_id = (int) $order_id;
            if ($order_id <= 0) {
                return redirect('admin/orders');
            }

            $order = model(Orders_model::class)
                ->select('id, partner_id, status, address, order_latitude, order_longitude')
                ->where('id', $order_id)
                ->first();

            if (empty($order)) {
                return redirect('admin/orders');
            }

            $order['invoice_no'] = 'INV-' . $order['id'];

            $liveTracking = model(\App\Models\LiveTrackingModel::class)
                ->where('order_id', $order_id)
                ->first();

            $leadHandyman = $this->db->table('booking_handymen bh')
                ->select('u.username, bh.status as handyman_status')
                ->join('users u', 'u.id = bh.handyman_id')
                ->where('bh.order_id', $order_id)
                ->where('bh.is_lead', 1)
                ->get()->getRowArray();

            $trackerType = (!empty($leadHandyman) && ($leadHandyman['handyman_status'] ?? '') === 'on_the_way') ? 'handyman' : 'partner';

            if ($trackerType === 'partner') {
                $partnerRow = $this->db->table('users')
                    ->select('username')
                    ->where('id', (int) $order['partner_id'])
                    ->get()->getRowArray();
                $trackerUsername = $partnerRow['username'] ?? labels('provider', 'Provider');
            } else {
                $trackerUsername = $leadHandyman['username'] ?? labels('handyman', 'Handyman');
            }

            setPageInfo($this->data, labels('live_tracking', 'Live Tracking') . ' | ' . labels('admin_panel', 'Admin Panel'), 'live_tracking');
            $this->data['order'] = $order;
            $this->data['live_tracking'] = $liveTracking;
            $this->data['tracker_type'] = $trackerType;
            $this->data['handyman_name'] = $trackerUsername;

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/Orders.php - live_tracking()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return redirect('admin/orders');
        }
    }

    public function tracker_location($order_id = 0)
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON(['error' => true])->setStatusCode(403);
        }

        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return $this->response->setJSON(['error' => true]);
        }

        $orderRow = model(Orders_model::class)
            ->select('status')
            ->where('id', $order_id)
            ->first();

        if (empty($orderRow)) {
            return $this->response->setJSON(['error' => true])->setStatusCode(404);
        }

        $orderStatus = $orderRow['status'] ?? '';

        $leadRow = $this->db->table('booking_handymen')
            ->select('status')
            ->where('order_id', $order_id)
            ->where('is_lead', 1)
            ->get()->getRowArray();
        $handymanStatus = $leadRow['status'] ?? '';
        $trackerType = ($handymanStatus === 'on_the_way') ? 'handyman' : 'partner';

        $cacheKey = 'live_tracking_order_' . $order_id;
        $cached = cache($cacheKey);
        if ($cached !== null) {
            $cached['handyman_status'] = $handymanStatus;
            $cached['tracker_type'] = $trackerType;
            $cached['order_status'] = $orderStatus;
            return $this->response->setJSON($cached);
        }

        $liveTracking = model(\App\Models\LiveTrackingModel::class)
            ->select('latitude, longitude, updated_at')
            ->where('order_id', $order_id)
            ->first();

        if (empty($liveTracking)) {
            return $this->response->setJSON(['error' => true, 'message' => 'no_data', 'handyman_status' => $handymanStatus, 'tracker_type' => $trackerType, 'order_status' => $orderStatus]);
        }

        $result = [
            'error' => false,
            'latitude' => (float) $liveTracking['latitude'],
            'longitude' => (float) $liveTracking['longitude'],
            'updated_at' => $liveTracking['updated_at'],
            'handyman_status' => $handymanStatus,
            'tracker_type' => $trackerType,
            'order_status' => $orderStatus,
        ];

        cache()->save($cacheKey, $result, 15);

        return $this->response->setJSON($result);
    }

    public function get_slots()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $order_id = $this->request->getPost('id');
            $date = $this->request->getPost('date');
            $partner_row = fetch_details('orders', ['id' => $order_id], ['partner_id', 'duration']);
            $partner_id = (int) ($partner_row[0]['partner_id'] ?? 0);
            $duration = (int) ($partner_row[0]['duration'] ?? 0);
            $slots = service('slot')->getAvailableSlots($partner_id, $date, $duration, (int) $this->userId);
            return $this->response->setJSON($slots);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/admin/Orders.php - get_slots(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
