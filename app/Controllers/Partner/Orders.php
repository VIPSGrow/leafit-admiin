<?php

namespace App\Controllers\Partner;

use App\Models\Orders_model;
use App\Models\BookingHandymenModel;
use App\Models\HandymanDetailsModel;
use App\Models\LiveTrackingModel;
use App\Models\Transaction_model;
use App\Models\Users_model;

class Orders extends Partner
{
    protected $validation;

    public $orders;
    public $data;
    public function __construct()
    {
        parent::__construct();
        $this->orders = new Orders_model();
        helper('ResponceServices');
    }
    public function index()
    {
        if ($this->isLoggedIn && !$this->userIsAdmin) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            setPageInfo($this->data, labels('bookings', 'Bookings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'orders');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function list()
    {
        try {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            $orders_model = new Orders_model();
            $orders = $orders_model->listForPartnerBookingsTable($this->userId);

            return $this->response->setJSON($orders);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    // FullCalendar event source for partner Bookings Calendar tab.
    // Scoped to current partner. Title is "INV-{order_id}"; click navigates
    // to partner/orders/view_orders/{id}.
    public function orders_calendar()
    {
        try {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return $this->response->setJSON(['events' => []]);
            }

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

            $db = \Config\Database::connect();

            $builder = $db->table('orders o')
                ->select("o.id, o.date_of_service, o.starting_time, o.ending_time,
                          o.status, o.final_total, o.partner_id, o.user_id,
                          u.username AS customer_name", false)
                ->join('users u', 'u.id = o.user_id')
                ->join('order_services os', 'os.order_id = o.id')
                ->where('o.partner_id', $this->userId)
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

            if ($search !== '') {
                $escapedSearch = $db->escapeLikeString($search);
                $builder->groupStart()
                    ->like('o.id', $escapedSearch)
                    ->orLike('u.username', $escapedSearch)
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
                    'url' => base_url('partner/orders/veiw_orders/' . $row['id']),
                    'extendedProps' => [
                        'order_id' => (int) $row['id'],
                        'status' => (string) $row['status'],
                        'customer_name' => (string) ($row['customer_name'] ?? ''),
                        'starting_time' => (string) ($row['starting_time'] ?? ''),
                        'ending_time' => (string) ($row['ending_time'] ?? ''),
                        'final_total' => (string) ($row['final_total'] ?? ''),
                    ],
                ];
            }

            return $this->response->setJSON(['events' => $response]);
        } catch (\Throwable $th) {
            log_message('error', 'app/Controllers/Partner/Orders.php - orders_calendar(): ' . $th->getMessage() . ' @ ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setJSON(['events' => []]);
        }
    }

    public function view_orders()
    {
        try {
            $uri = service('uri');
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('partner/login');
            } else {
                $this->orders = new Orders_model();
                setPageInfo($this->data, labels('bookings', 'Bookings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'order_details');
                $order_id = $uri->getSegments()[3];
                $order_data = fetch_details('orders', ['id' => $order_id]);
                if (empty($order_data)) {
                    return redirect('partner/orders');
                }
                $where['o.id'] = $order_id;
                $order_details = $this->orders->list(true, '', 10, 0, '', '', $where);
                if ((empty($order_details['data']))) {
                    return redirect('partner/orders');
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
                $additional_charge_tax_amount = 0;
                if (!empty($od['additional_charges']) && is_array($od['additional_charges'])) {
                    foreach ($od['additional_charges'] as $charge) {
                        $additional_charge_tax_amount += floatval($charge['tax_amount'] ?? 0);
                    }
                }
                $order_total = floatval(str_replace(',', '', $od['total'] ?? 0));
                $order_subtotal = $order_total - $order_tax_amount;
                $this->data['order_subtotal'] = format_amount($order_subtotal);
                $this->data['order_tax_amount'] = format_amount($order_tax_amount + $additional_charge_tax_amount);
                $this->data['additional_charge_tax_amount'] = format_amount($additional_charge_tax_amount);
                $this->data['order_tax_type'] = !empty($od['services']) ? ($od['services'][0]['tax_type'] ?? '') : '';
                $this->data['order_final_total'] = format_amount(floatval(str_replace(',', '', $od['final_total'] ?? 0)));

                $data = get_settings('general_settings', true);
                $this->data['currency'] = $data['currency'];
                $this->data['promocode_discount'] = $od['promo_discount'] ?? 0;
                // Respect the provider's advance booking window so the reschedule calendar stays honest.
                $advance_booking_days = isset($order_details['data'][0]['advance_booking_days']) ? intval($order_details['data'][0]['advance_booking_days']) : null;
                $timezone = $data['system_timezone'] ?? date_default_timezone_get();
                $today = new \DateTime('now', new \DateTimeZone($timezone));
                $min_reschedule_date = $today->format('Y-m-d');
                $max_reschedule_date = null;
                if ($advance_booking_days !== null) {
                    if ($advance_booking_days > 0) {
                        $max_reschedule_date = (clone $today)->modify('+' . $advance_booking_days . ' days')->format('Y-m-d');
                    } else {
                        $max_reschedule_date = $min_reschedule_date;
                    }
                }
                $this->data['advance_booking_days'] = $advance_booking_days;
                $this->data['reschedule_min_date'] = $min_reschedule_date;
                $this->data['reschedule_max_date'] = $max_reschedule_date;
                $sub_orders = fetch_details('orders', ['parent_id' => $order_id]);
                $this->data['sub_order'] = $sub_orders;
                $partner_personal_data = fetch_details('users', ['id' => $this->userId], ['email'])[0];
                $this->data['personal_data'] = $partner_personal_data;
                $this->data['cancel_reasons'] = get_cancel_reasons_for_panel();

                $raw_order = $order_data[0] ?? [];
                $cancel_info = ['reason' => null, 'additional_info' => null];
                if (!empty($raw_order['cancel_reason_id'])) {
                    foreach ($this->data['cancel_reasons'] as $cr) {
                        if ((int) $cr['id'] === (int) $raw_order['cancel_reason_id']) {
                            $cancel_info['reason'] = $cr['reason'];
                            break;
                        }
                    }
                }
                $cancel_info['additional_info'] = $raw_order['cancel_additional_info'] ?? null;
                $this->data['cancel_info'] = $cancel_info;

                // Handyman assignment data
                $handymanModel = new HandymanDetailsModel();
                $db = \Config\Database::connect();

                $assignedRows = $db->table('booking_handymen oh')
                    ->select('oh.handyman_id AS id, u.username, u.image, oh.status AS handyman_status, oh.is_lead')
                    ->join('users u', 'u.id = oh.handyman_id')
                    ->where('oh.order_id', $order_id)
                    ->get()->getResultArray();
                $this->data['assigned_handymen'] = $assignedRows;
                $this->data['assigned_handyman_ids'] = array_column($assignedRows, 'id');

                // Lead handyman status — used to decide live tracking button visibility
                $leadHandymanStatus = null;
                foreach ($assignedRows as $hr) {
                    if (!empty($hr['is_lead'])) {
                        $leadHandymanStatus = $hr['handyman_status'];
                        break;
                    }
                }
                $this->data['order_details']['lead_handyman_status'] = $leadHandymanStatus;

                $allAvailable = $handymanModel->getForAssignment($this->userId);
                $assignedIdSet = array_flip($this->data['assigned_handyman_ids']);
                $fileService = service('fileService');

                $slotSettingsModel = new \App\Models\ProviderSlotSettings_model();
                $slotSettings = $slotSettingsModel->findByPartner($this->userId) ?? [];
                $bufferBefore = (int) ($slotSettings['buffer_before'] ?? 0);
                $bufferAfter = (int) ($slotSettings['buffer_after'] ?? 0);
                $orderConflicts = $handymanModel->getConflictsForOrder(
                    $this->userId,
                    (int) $order_id,
                    $raw_order['date_of_service'] ?? '',
                    $raw_order['starting_time'] ?? '',
                    $raw_order['ending_time'] ?? '',
                    $bufferBefore,
                    $bufferAfter
                );
                // map: handyman_id => conflicting_order_id
                $conflictMap = array_column($orderConflicts, 'current_order_id', 'id');

                $mergedHandymen = [];
                foreach ($allAvailable as &$h) {
                    $h['image_url'] = $fileService->url($h['image'], 'profile');
                    $h['conflicting_order_id'] = isset($conflictMap[$h['id']]) ? (int) $conflictMap[$h['id']] : null;
                    if (isset($assignedIdSet[$h['id']]))
                        continue;
                    $mergedHandymen[] = $h;
                }
                unset($h);
                // available handymen first, conflicting last
                usort($mergedHandymen, fn($a, $b) => ($a['conflicting_order_id'] !== null) <=> ($b['conflicting_order_id'] !== null));
                $this->data['handymen'] = $mergedHandymen;

                return view('backend/partner/template', $this->data);
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - view_orders()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function order_summary_table($order_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $orders_model = new Orders_model();
            $data = get_settings('general_settings', true);
            $currency = $data['currency'];
            $orders = $orders_model->invoice($order_id);
            $services = $orders['order']['services'];
            $total = count($services);
            $subtotal = 0.00;
            $tax_amount = 0;
            if (!empty($orders)) {
                $i = 0;
                $rows = [];
                foreach ($services as $service) {
                    $subtotal += floatval($service['sub_total']);
                    $tax_amount = ($service['tax_type'] == "excluded") ? $tax_amount + floatval($service['tax_amount'] * $service['quantity']) : $tax_amount;
                    $operations = '<button class="btn btn-danger btn-sm cancel_service" data-id="' . $service['id'] . '"> <i class="fas fa-trash"></i> </button>';
                    if (empty($service['service_image'])) {
                        $profile = '
                        <a href="#" id="pop">
                            <img id="profile_picture" onerror="this.onerror=null;this.src=\'' . base_url('public/backend/assets/default.png') . '\'" 
                                 src="' . base_url('public/backend/assets/default.png') . '" 
                                 height="50px" width="50px" class="rounded-circle mr-4" style="border-radius:5px !important">
                        </a>';
                    } else {
                        $profile =
                            '<a href="#" id="pop">
                                <img id="profile_picture"  onerror="this.onerror=null;this.src=\'' . base_url('public/backend/assets/default.png') . '\'"  src="' . base_url($service['service_image']) . '" height="50px" width="50px"  class="rounded-circle mr-4" style="border-radius:5px !important">
                         </a>';
                    }
                    $profile =
                        '<li class="media p-2" >' . $profile . ' <div class="media-body"> <div class="media-title mt-3">' . $service['service_title'] . '</div>
                       </div></li>';
                    $rows[$i] = [
                        'service_title' => $profile,
                        'price' => $currency . number_format((float) $service['price'], 2, '.', ''),
                        'discount' => ($service['discount_price'] == 0) ? $currency . '0.00' : $currency . number_format((float) $service['price'] - (float) $service['discount_price'], 2, '.', ''),
                        'net_amount' => ($service['discount_price'] != 0) ? $currency . number_format((float) $service['discount_price'], 2, '.', '') : $currency . number_format((float) $service['price'], 2, '.', ''),
                        'tax' => ($service['tax_type'] == "excluded") ? $service['tax_percentage'] . '%' : '0%',
                        'tax_amount' => ($service['tax_type'] == "excluded") ? $service['tax_amount'] : 0,
                        'quantity' => ucwords($service['quantity']),
                        'duration' => ucwords($service['duration'] * $service['quantity']),
                        'subtotal' => $currency . number_format((float) $service['sub_total'], 2, '.', ''),
                        'operations' => $operations,
                    ];
                    $i++;
                }
                $array['total'] = $total;
                $array['rows'] = $rows;
                echo json_encode($array);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - order_summary_table()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function invoice_table($order_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $orders_model = new Orders_model();
            $data = get_settings('general_settings', true);
            $currency = $data['currency'];
            $orders = $orders_model->invoice($order_id);
            $services = $orders['order']['services'];
            $total = count($services);
            if (!empty($orders)) {
                $i = 0;
                $rows = [];

                // Recalculate totals from services using same logic as admin/customer invoice (V1::invoice_download)
                $sum_net_amount = 0;
                $sum_tax_amount = 0;

                foreach ($services as $service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);

                    // Stored tax_amount is per unit; line tax = tax_amount * quantity (match admin/customer)
                    $stored_tax = (float) ($service['tax_amount'] ?? 0);
                    $line_tax = $stored_tax * $qty;

                    // Unit price (discounted or original); line net = unit price * qty
                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;

                    $sum_net_amount += $service['tax_type'] == 'included' ? $line_net - $line_tax : $service['sub_total'] - $line_tax;
                    $sum_tax_amount += $line_tax;

                    $operations = '<button class="btn btn-danger btn-sm cancel_service" data-id="' . $service['id'] . '"> <i class="fas fa-trash"></i> </button>';
                    // Row display: net_amount = unit price; tax_amount = line tax; subtotal = line subtotal (match admin)
                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency . "0.00" : $currency . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency . number_format($unitPrice, 2, '.', ''),
                        'tax' => ($service['tax_percentage'] ?? '') . '%',
                        'tax_amount' => $currency . number_format($line_tax, 2, '.', ''),
                        'quantity' => ucwords((string) $qty),
                        'subtotal' => $currency . number_format($service['sub_total'], 2, '.', ''),
                        'operations' => $operations,
                    ];
                    $i++;
                }

                // Use recalculated total (subtotal without tax) to match admin invoice
                $recalc_total = number_format($sum_net_amount, 2, '.', '');

                $row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark'>" . labels('total', 'Total') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $recalc_total . "</strong>",
                    'operations' => "",
                ];

                // Tax row: show total tax amount just below Total (same as admin invoice)
                $tax_row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark'>" . labels('tax', 'Tax') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . number_format($sum_tax_amount, 2, '.', '') . "</strong>",
                    'operations' => "",
                ];

                if ($orders['order']['visiting_charges'] != "0") {
                    $visiting_charges = [
                        'service_title' => "",
                        'price' => "",
                        'discount' => "",
                        'net_amount' => "",
                        'tax' => "",
                        'tax_amount' => "",
                        'quantity' => "<strong class='text-dark '>" . labels('visiting_charges', 'Visiting Charges') . "</strong>",
                        'subtotal' => "<strong class='text-dark '>" . $currency . $orders['order']['visiting_charges'] . "</strong>",
                        'operations' => "",
                    ];
                }

                $promo_code_discount = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark '>" . labels('promocode_discount', 'Promo Code Discount') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $orders['order']['promo_discount'] . "</strong>",
                    'operations' => "",
                ];

                // Final total: use final_total when available (match admin), else total - promo_discount
                $order_row = $orders['order'];
                $payble_amount = isset($order_row['final_total']) ? ($order_row['final_total'] - $order_row['promo_discount']) : ($order_row['total'] - $order_row['promo_discount']);
                $final_total_row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark '>" . labels('final_total', 'Final Total') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $payble_amount . "</strong>",
                    'operations' => "",
                ];

                // Additional charges: show each extra charge as its own row (same behaviour as admin invoice table)
                if (!empty($order_row['additional_charges'])) {
                    $additional_charges = json_decode($order_row['additional_charges'], true);
                    if (is_array($additional_charges)) {
                        foreach ($additional_charges as $charge) {
                            $rows[] = [
                                'service_title' => "",
                                'price' => "",
                                'discount' => "",
                                'net_amount' => "",
                                'tax' => "",
                                'tax_amount' => "",
                                'quantity' => "<strong class='text-dark'>" . ($charge['name'] ?? '') . "</strong>",
                                'subtotal' => "<strong class='text-dark'>" . $currency . ($charge['charge'] ?? 0) . "</strong>",
                                'operations' => "",
                            ];
                        }
                    }
                }

                // Push summary rows in same order as admin: Total, Tax, visiting, promo, final
                if (!empty($rows)) {
                    array_push($rows, $row);
                    array_push($rows, $tax_row);
                    if ($orders['order']['visiting_charges'] != "0") {
                        array_push($rows, $visiting_charges);
                    }
                    array_push($rows, $promo_code_discount);
                    array_push($rows, $final_total_row);
                }
                $array['total'] = $total;
                $array['rows'] = $rows;
                echo json_encode($array);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - invoice_table()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function invoice()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('partner/login');
            } else {
                $uri = service('uri');
                $order_id = $uri->getSegments()[3];
                $order_data = fetch_details('orders', ['id' => $order_id]);
                if (empty($order_data)) {
                    return redirect('partner/orders');
                }
                $this->orders = new Orders_model();
                setPageInfo($this->data, labels('bookings', 'Bookings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'invoice');
                $order_details = $this->orders->invoice($order_id);
                $subtotal = 0.00;
                foreach ($order_details['order']['services'] as $service) {
                    $subtotal += floatval($service['sub_total']);
                }
                $promocode_discount = 0.00;
                if (isset($order_details) && !empty($order_details['order']['promo_discount'])) {
                    $promocode_discount = intval($order_details['order']['total'] + $order_details['order']['visiting_charges']) * intval($order_details['order']['promo_discount']) / 100;
                }
                $this->data['promocode_discount'] = $promocode_discount;
                $this->data['subtotal'] = $subtotal;
                $data = get_settings('general_settings', true);
                $this->data['currency'] = $data['currency'];
                $this->data['logo'] = $data['partner_logo'];
                $this->data['order'] = $order_details['order'];
                return view('backend/partner/template', $this->data);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - invoice()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update_order_status()
    {
        try {
            if ($this->isLoggedIn && $this->userIsPartner) {
                $ordersModel = new Orders_model();
                $transactionsModel = new Transaction_model();
                $order_id = $this->request->getPost('order_id');
                $status = $this->request->getPost('status');
                $date = $this->request->getPost('rescheduled_date');
                $selected_time = $this->request->getPost('reschedule');
                $otp = $this->request->getPost('otp');
                $uploadedFiles = $this->request->getFiles();
                $orderRow = $ordersModel->select('partner_id, payment_method')->where('id', $order_id)->first();
                $partner_id = $orderRow['partner_id'];


                if (($orderRow['payment_method'] ?? '') !== 'cod') {
                    $transaction = $transactionsModel->where('order_id', $order_id)->orderBy('created_at', 'DESC')->first();

                    if (($transaction['status'] ?? '') !== 'success') {
                        return $this->response->setJSON([
                            'error' => true,
                            'message' => payment_block_message($transaction['status'] ?? null),
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                        ]);
                    }
                }

                if ($status == "rescheduled" && $selected_time == "") {
                    $response = [
                        'error' => true,
                        'message' => labels('please_select_reschedule_timing', "Please select reschedule timing"),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }
                // Slot engine performs the lock-first availability check inside validate_order_status.
                $is_provider_available = ['error' => false];
                if ($status == "rescheduled" && !$is_provider_available['error']) {
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    // - Provider updates => notify admin + customer (not provider).
                    $response = validate_order_status($order_id, $status, $date, $selected_time, null, null, null, $this->userId, get_current_language(), 'provider');

                    // Add event tracking data if status update was successful
                    if (is_array($response) && isset($response['error']) && $response['error'] == false) {
                        // Get order details for event tracking
                        // Note: orders table uses 'user_id' column, not 'customer_id'
                        $orderData = fetch_details('orders', ['id' => $order_id], ['user_id']);
                        $customerId = !empty($orderData) ? $orderData[0]['user_id'] : '';

                        // Add event data to response
                        if (!isset($response['data'])) {
                            $response['data'] = [];
                        }
                        $response['data']['clarity_event'] = 'booking_status_updated';
                        $response['data']['booking_id'] = $order_id;
                        $response['data']['status'] = $status;
                        $response['data']['customer_id'] = $customerId;
                    }

                    $this->assignInlineHandymen($order_id, $response);

                    return json_encode($response);
                } else {

                    if ($status == "completed") {
                        // Pass the actor (provider) user_id so notifications are routed correctly.
                        $response = validate_order_status($order_id, $status, '', '', $otp, "", null, $this->userId, get_current_language(), 'provider');
                    } elseif ($status == "started") {
                        // Pass the actor (provider) user_id so notifications are routed correctly.
                        $response = validate_order_status($order_id, $status, '', '', '', isset($uploadedFiles) ? $uploadedFiles : "", null, $this->userId, get_current_language(), 'provider');
                    } elseif ($status == "booking_ended") {
                        $additionalCharge = $this->request->getPost('booking_ended_additional_charges') ?? '';
                        // Pass the actor (provider) user_id so notifications are routed correctly.
                        $response = validate_order_status($order_id, $status, '', '', '', isset($uploadedFiles) ? $uploadedFiles : "", $additionalCharge, $this->userId, get_current_language(), 'provider');
                    } elseif ($status == "cancelled") {
                        $cancel_reason_id = $this->request->getPost('cancel_reason_id');
                        $additional_info = $this->request->getPost('additional_info');
                        $cancel_validation = validate_cancel_reason($cancel_reason_id, $additional_info);
                        if ($cancel_validation['error']) {
                            $cancel_validation['csrfName'] = csrf_token();
                            $cancel_validation['csrfHash'] = csrf_hash();
                            return $this->response->setJSON($cancel_validation);
                        }
                        $response = validate_order_status($order_id, $status, '', '', '', '', '', $this->userId, get_current_language(), 'provider');
                        if (!$response['error']) {
                            update_details([
                                'cancel_reason_id' => (int) $cancel_reason_id,
                                'cancel_additional_info' => $cancel_validation['additional_info'],
                                'cancelled_by' => 'provider',
                            ], ['id' => $order_id], 'orders');
                        }
                    } elseif ($status == "on_the_way") {
                        $orderCheck = $ordersModel->select('status, address_id')
                            ->where('id', $order_id)
                            ->where('partner_id', $this->userId)
                            ->first();
                        if (empty($orderCheck) || (int) $orderCheck['address_id'] === 0) {
                            $response = ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                        } elseif (!in_array($orderCheck['status'], ['confirmed', 'rescheduled'])) {
                            $response = ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                        } else {
                            $db = \Config\Database::connect();
                            $db->transStart();

                            $ordersModel->set(['status' => 'on_the_way', 'status_changed_by_type' => 'provider', 'status_changed_by_id' => $this->userId])->where('id', $order_id)->update();
                            $latitude = trim((string) $this->request->getPost('latitude'));
                            $longitude = trim((string) $this->request->getPost('longitude'));
                            $this->recordLiveTrackingLocation($order_id, $this->userId, $latitude ?: null, $longitude ?: null);

                            $db->transComplete();
                            if (!$db->transStatus()) {
                                $response = ['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                            } else {
                                $response = ['error' => false, 'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Status updated successfully'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                            }
                        }
                    } elseif ($status == "arrived") {
                        $orderCheck = $ordersModel->select('status')
                            ->where('id', $order_id)
                            ->where('partner_id', $this->userId)
                            ->first();
                        if (empty($orderCheck)) {
                            $response = ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                        } elseif ($orderCheck['status'] !== 'on_the_way') {
                            $response = ['error' => true, 'message' => labels(ERROR_OCCURED, 'An error occurred'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                        } else {
                            $ordersModel->set(['status' => 'arrived', 'status_changed_by_type' => 'provider', 'status_changed_by_id' => $this->userId])->where('id', $order_id)->update();
                            $response = ['error' => false, 'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Status updated successfully'), 'data' => [], 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()];
                        }
                    } else {
                        // Pass the actor (provider) user_id so notifications are routed correctly.
                        $response = validate_order_status($order_id, $status, null, null, null, null, null, $this->userId, get_current_language(), 'provider');
                    }

                    // Provider-driven status change: mirror it onto assigned handymen so the
                    // handyman app doesn't show a stale status (booking_handymen.status).
                    if (
                        is_array($response) && isset($response['error']) && $response['error'] == false
                        && in_array($status, ['on_the_way', 'arrived', 'started', 'booking_ended', 'completed'], true)
                    ) {
                        (new BookingHandymenModel())->syncStatusForOrder((int) $order_id, $status);
                    }

                    // Add event tracking data if status update was successful
                    if (is_array($response) && isset($response['error']) && $response['error'] == false) {
                        // Get order details for event tracking
                        // Note: orders table uses 'user_id' column, not 'customer_id'
                        $orderData = fetch_details('orders', ['id' => $order_id], ['user_id']);
                        $customerId = !empty($orderData) ? $orderData[0]['user_id'] : '';

                        // Determine specific event type based on status
                        $eventType = 'booking_status_updated';
                        if ($status == 'accepted' || $status == 'confirmed') {
                            $eventType = 'booking_accepted';
                        } elseif ($status == 'rejected') {
                            $eventType = 'booking_rejected';
                        } elseif ($status == 'cancelled') {
                            $eventType = 'booking_cancelled';
                        } elseif ($status == 'completed') {
                            $eventType = 'booking_completed';
                        }

                        // Add event data to response
                        if (!isset($response['data'])) {
                            $response['data'] = [];
                        }
                        $response['data']['clarity_event'] = $eventType;
                        $response['data']['booking_id'] = $order_id;
                        $response['data']['status'] = $status;
                        $response['data']['customer_id'] = $customerId;
                    }

                    if (in_array($status, ['confirmed', 'started'])) {
                        $this->assignInlineHandymen($order_id, $response);
                    }

                    return json_encode($response);
                }
            } else {
                return redirect('admin/login');
            }
        } catch (\Exception $e) {
            // throw $e;
            log_the_responce($e, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - update_order_status()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function assign_handyman()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorised', 'Unauthorised')]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $handyman_ids = $this->request->getPost('handyman_ids');

            $order = fetch_details('orders', ['id' => $order_id, 'partner_id' => $this->userId], ['id', 'status', 'date_of_service', 'starting_time', 'ending_time']);
            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (!in_array($order[0]['status'] ?? '', ['confirmed', 'started', 'rescheduled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $handymanModel = new HandymanDetailsModel();
            $handymen = $handymanModel->getForAssignment($this->userId);
            $validIds = array_column($handymen, 'id');

            if (empty($handyman_ids) || !is_array($handyman_ids)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('select_handyman', 'Select Handyman'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $slotSettingsModel = new \App\Models\ProviderSlotSettings_model();
            $slotSettings = $slotSettingsModel->findByPartner($this->userId) ?? [];
            $bufferBefore = (int) ($slotSettings['buffer_before'] ?? 0);
            $bufferAfter = (int) ($slotSettings['buffer_after'] ?? 0);
            $conflicts = $handymanModel->getConflictsForOrder(
                $this->userId,
                $order_id,
                $order[0]['date_of_service'] ?? '',
                $order[0]['starting_time'] ?? '',
                $order[0]['ending_time'] ?? '',
                $bufferBefore,
                $bufferAfter
            );
            $conflictIds = array_flip(array_column($conflicts, 'id'));

            $db = \Config\Database::connect();
            $existingRows = $db->table('booking_handymen')->select('handyman_id, is_lead')->where('order_id', $order_id)->get()->getResultArray();
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
                    //  $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
                    $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'status' => 'accepted', 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
                }
            }

            if (!empty($toInsert) && !$hasLeadAlready) {
                $toInsert[0]['is_lead'] = 1;
            }

            if (!empty($toInsert)) {
                $db->table('booking_handymen')->insertBatch($toInsert);
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
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Partner/Orders.php - assign_handyman()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        }
    }

    public function unassign_handyman()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorised', 'Unauthorised')]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $handyman_id = (int) $this->request->getPost('handyman_id');

            $order = fetch_details('orders', ['id' => $order_id, 'partner_id' => $this->userId], ['id', 'status']);
            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (!in_array($order[0]['status'] ?? '', ['confirmed', 'rescheduled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $db = \Config\Database::connect();
            $bookingHandyman = $db->table('booking_handymen')
                ->where('order_id', $order_id)
                ->where('handyman_id', $handyman_id)
                ->get()->getRowArray();

            if (empty($bookingHandyman)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_not_found', 'Handyman not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (!in_array($bookingHandyman['status'] ?? '', ['assigned', 'accepted'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_cannot_be_unassigned_in_current_status', 'Handyman cannot be unassigned in their current status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $wasLead = (int) ($bookingHandyman['is_lead'] ?? 0) === 1;

            $db->transStart();

            $db->table('booking_handymen')
                ->where('order_id', $order_id)
                ->where('handyman_id', $handyman_id)
                ->delete();

            if ($wasLead) {
                $remaining = $db->table('booking_handymen')->select('handyman_id')->where('order_id', $order_id)->limit(1)->get()->getRowArray();
                if (!empty($remaining)) {
                    $db->table('booking_handymen')->where('order_id', $order_id)->where('handyman_id', $remaining['handyman_id'])->set(['is_lead' => 1])->update();
                }
            }

            $db->transComplete();
            if (!$db->transStatus()) {
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
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Partner/Orders.php - unassign_handyman()');
            return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        }
    }

    public function set_lead_handyman()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return $this->response->setJSON(['error' => true, 'message' => labels('unauthorised', 'Unauthorised')]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $handyman_id = (int) $this->request->getPost('handyman_id');

            $order = fetch_details('orders', ['id' => $order_id, 'partner_id' => $this->userId], ['id', 'status']);
            if (empty($order)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('order_not_found', 'Order not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            if (in_array($order[0]['status'] ?? '', ['on_the_way', 'arrived', 'started', 'completed', 'cancelled'])) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_assignment_not_allowed_for_this_status', 'Handyman assignment is not allowed for this booking status'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $db = \Config\Database::connect();
            $row = $db->table('booking_handymen')->where('order_id', $order_id)->where('handyman_id', $handyman_id)->get()->getRowArray();
            if (empty($row)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('handyman_not_found', 'Handyman not found'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $db->transStart();
            $db->table('booking_handymen')->where('order_id', $order_id)->set(['is_lead' => 0])->update();
            $db->table('booking_handymen')->where('order_id', $order_id)->where('handyman_id', $handyman_id)->set(['is_lead' => 1])->update();
            $db->transComplete();
            if (!$db->transStatus()) {
                return $this->response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('lead_handyman_set_successfully', 'Lead handyman set successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Partner/Orders.php - set_lead_handyman()');
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

        $handymanModel = new HandymanDetailsModel();
        $validIds = array_column($handymanModel->getForAssignment($this->userId), 'id');
        $db = \Config\Database::connect();
        $existingRows = $db->table('booking_handymen')->select('handyman_id, is_lead')->where('order_id', $order_id)->get()->getResultArray();
        $existingIds = array_column($existingRows, 'handyman_id');
        $hasLeadAlready = !empty(array_filter($existingRows, fn($r) => (int) $r['is_lead'] === 1));

        $toInsert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($handymanIds as $hid) {
            $hid = (int) $hid;
            if (in_array($hid, $validIds) && !in_array($hid, $existingIds)) {
                $toInsert[] = ['order_id' => $order_id, 'handyman_id' => $hid, 'status' => 'accepted', 'is_lead' => 0, 'assigned_at' => $now, 'assigned_by' => $this->userId, 'updated_at' => $now];
            }
        }
        if (!empty($toInsert) && !$hasLeadAlready) {
            $toInsert[0]['is_lead'] = 1;
        }
        if (!empty($toInsert)) {
            $db->table('booking_handymen')->insertBatch($toInsert);
        }
    }

    public function get_slots()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }
        $order_id = $this->request->getPost('id');
        $date = $this->request->getPost('date');
        if (empty($order_id) || empty($date)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(INVALID_BOOKING_OR_STATUS_DATA, "Invalid booking or status data"),
                'data' => []
            ]);
        }
        $order = fetch_details('orders', ['id' => $order_id], ['partner_id', 'duration']);
        if (empty($order)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(NO_PARTNER_FOUND, "No Partner Found"),
                'data' => []
            ]);
        }
        $partner_id = $order[0]['partner_id'];
        // Reschedule slots must fit this order's booked service duration.
        $order_duration = (int) ($order[0]['duration'] ?? 0);
        $settings = get_settings('general_settings', true);
        $timezone = $settings['system_timezone'] ?? date_default_timezone_get();

        // Validate the raw input so reschedule attempts can never bypass advance booking limits.
        $selected_date = \DateTime::createFromFormat('Y-m-d', $date, new \DateTimeZone($timezone));
        if (!$selected_date) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('invalid_date_format', "Invalid date format"),
                'data' => []
            ]);
        }
        $selected_date->setTime(0, 0);
        $today = new \DateTime('now', new \DateTimeZone($timezone));
        $today->setTime(0, 0);
        if ($selected_date < $today) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(PLEASE_SELECT_UPCOMING_DATE, "Please select upcoming date"),
                'data' => []
            ]);
        }
        $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['advance_booking_days']);
        if (!empty($partner_data)) {
            $allowed_advanced_booking_days = intval($partner_data[0]['advance_booking_days']);
            if ($allowed_advanced_booking_days === 0 && $selected_date > $today) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ADVANCED_BOOKING_FOR_THIS_PARTNER_IS_NOT_AVAILABLE, "Advanced booking for this partner is not available"),
                    'data' => []
                ]);
            }
            if ($allowed_advanced_booking_days > 0) {
                $max_available_date = (clone $today)->modify('+' . $allowed_advanced_booking_days . ' days');
                if ($selected_date > $max_available_date) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(YOU_CAN_NOT_CHOOSE_DATE_BEYOND_AVAILABLE_BOOKING_DAYS, "You can not choose date beyond available booking days") . ' ' . $allowed_advanced_booking_days . ' ' . labels(DAYS, "days"),
                        'data' => []
                    ]);
                }
            }
        } else {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(NO_PARTNER_FOUND, "No Partner Found"),
                'data' => []
            ]);
        }
        $slots = service('slot')->getAvailableSlots((int) $partner_id, $selected_date->format('Y-m-d'), $order_duration, (int) $this->userId);
        return $this->response->setJSON($slots);
    }
    public function newlist()
    {
        $orders_model = new Orders_model();
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 5;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where['o.partner_id'] = $this->userId;

        // Get the data from the model
        $result = $orders_model->dashboardRecentBookingList($limit, $offset, $sort, $order, $where);

        // Decode the JSON result to modify the operations column
        $data = json_decode($result, true);

        // Modify the operations column to show eye icon instead of dropdown
        if (isset($data['rows']) && is_array($data['rows'])) {
            foreach ($data['rows'] as &$row) {
                // Replace the dropdown operations with a simple eye icon for viewing
                $row['operations'] = '<a href="' . base_url('partner/orders/veiw_orders/' . $row['id']) . '" class="btn btn-primary btn-sm" title="' . labels('view_the_order', 'View the Order') . '"><i class="fa fa-eye" aria-hidden="true"></i></a>';
            }
        }

        // Return the modified data as JSON
        return json_encode($data);
    }

    public function tracker_location($order_id = 0)
    {
        if (!$this->isLoggedIn || $this->userIsAdmin) {
            return $this->response->setJSON(['error' => true])->setStatusCode(403);
        }

        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return $this->response->setJSON(['error' => true]);
        }

        $db = \Config\Database::connect();
        $orderRow = $db->table('orders')
            ->select('status')
            ->where('id', $order_id)
            ->where('partner_id', $this->userId)
            ->get()->getRowArray();

        if (empty($orderRow)) {
            return $this->response->setJSON(['error' => true])->setStatusCode(403);
        }

        $orderStatus = $orderRow['status'] ?? '';

        $leadRow = $db->table('booking_handymen')
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

    public function live_tracking($order_id = 0)
    {
        try {
            if (!$this->isLoggedIn || $this->userIsAdmin) {
                return redirect('partner/login');
            }

            $order_id = (int) $order_id;
            if ($order_id <= 0) {
                return redirect('partner/orders');
            }

            $db = \Config\Database::connect();
            $order = $db->table('orders')
                ->select('id, status, address, order_latitude, order_longitude')
                ->where('id', $order_id)
                ->where('partner_id', $this->userId)
                ->get()->getRowArray();

            if (!empty($order)) {
                $order['invoice_no'] = 'INV-' . $order['id'];
            }

            if (empty($order)) {
                return redirect('partner/orders');
            }

            $liveTracking = model(LiveTrackingModel::class)
                ->where('order_id', $order_id)
                ->first();

            $leadHandyman = $db->table('booking_handymen bh')
                ->select('u.username, bh.status as handyman_status')
                ->join('users u', 'u.id = bh.handyman_id')
                ->where('bh.order_id', $order_id)
                ->where('bh.is_lead', 1)
                ->get()->getRowArray();

            $trackerType = (!empty($leadHandyman) && ($leadHandyman['handyman_status'] ?? '') === 'on_the_way') ? 'handyman' : 'partner';

            setPageInfo($this->data, labels('live_tracking', 'Live Tracking') . ' | ' . labels('provider_panel', 'Provider Panel'), 'live_tracking');
            $this->data['order'] = $order;
            $this->data['live_tracking'] = $liveTracking;
            $this->data['tracker_type'] = $trackerType;
            $this->data['handyman_name'] = ($trackerType === 'handyman')
                ? ($leadHandyman['username'] ?? labels('handyman', 'Handyman'))
                : ($this->data['user']['username'] ?? labels('provider', 'Provider'));

            return view('backend/partner/template', $this->data);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Partner/Orders.php - live_tracking()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return redirect('partner/orders');
        }
    }

    public function live_map()
    {
        try {
            if (!$this->isLoggedIn || $this->userIsAdmin) {
                return redirect('partner/login');
            }

            setPageInfo($this->data, labels('live_tracking', 'Live Tracking') . ' | ' . labels('provider_panel', 'Provider Panel'), 'live_map');

            return view('backend/partner/template', $this->data);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Partner/Orders.php - live_map()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return redirect('partner/orders');
        }
    }

    public function live_map_data()
    {
        try {
            if (!$this->isLoggedIn || $this->userIsAdmin) {
                return $this->response->setJSON(['error' => true])->setStatusCode(403);
            }

            $rows = model(LiveTrackingModel::class)->getActiveHandymenForPartner($this->userId);

            return JsonSuccess(labels('data_fetched_successfully', 'Data fetched successfully'), $rows);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Partner/Orders.php - live_map_data()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels('something_went_wrong', 'Something Went Wrong'));
        }
    }

    private function recordLiveTrackingLocation(int $orderId, int $userId, ?string $latitude = null, ?string $longitude = null): void
    {
        if (empty($latitude) || empty($longitude)) {
            $usersModel = model(Users_model::class);
            $user = $usersModel->select('latitude, longitude')->find($userId);
            $latitude = $user['latitude'] ?? null;
            $longitude = $user['longitude'] ?? null;
        }

        if (empty($latitude) || empty($longitude)) {
            return;
        }

        $liveTrackingModel = model(LiveTrackingModel::class);
        $trackingData = [
            'order_id' => $orderId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        $existing = $liveTrackingModel->where('order_id', $orderId)->first();
        if (!empty($existing)) {
            $liveTrackingModel->where('order_id', $orderId)->set($trackingData)->update();
        } else {
            $liveTrackingModel->insert($trackingData);
        }
    }

    public function update_partner_location()
    {
        try {
            if (!$this->isLoggedIn || $this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $orderId = (int) $this->request->getPost('order_id');
            $latitude = trim((string) $this->request->getPost('latitude'));
            $longitude = trim((string) $this->request->getPost('longitude'));

            if ($orderId <= 0 || $latitude === '' || $longitude === '') {
                return $this->response->setJSON(['error' => true, 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $db = \Config\Database::connect();
            $ownsOrder = $db->table('orders')
                ->where('id', $orderId)
                ->where('partner_id', $this->userId)
                ->where('status', 'on_the_way')
                ->countAllResults();

            if (!$ownsOrder) {
                return $this->response->setJSON(['error' => true, 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
            }

            $liveTrackingModel = model(\App\Models\LiveTrackingModel::class);
            $existing = $liveTrackingModel->where('order_id', $orderId)->first();

            $trackingData = [
                'order_id' => $orderId,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            if (!empty($existing)) {
                $liveTrackingModel->where('order_id', $orderId)->set($trackingData)->update();
            } else {
                $liveTrackingModel->insert($trackingData);
            }

            cache()->delete('live_tracking_order_' . $orderId);

            return $this->response->setJSON(['error' => false, 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Partner/Orders.php - update_partner_location()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return $this->response->setJSON(['error' => true, 'csrfName' => csrf_token(), 'csrfHash' => csrf_hash()]);
        }
    }
}
