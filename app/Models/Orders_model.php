<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;
use IonAuth\Libraries\IonAuth;
use App\Services\utility\PermissionService;

class Orders_model extends Model
{
    protected int $admin_id = 0;
    protected IonAuth $ionAuth;
    protected PermissionService $permissionService;

    /**
     * True when the logged-in user is an admin-panel user.
     * Covers both regular admins (IonAuth group) and super-admins
     * (PermissionService role). Evaluated once in the constructor
     * and reused everywhere — no repeated isAdmin() calls needed.
     */
    protected bool $isAdminPanel = false;

    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['partner_id', 'assigned_handyman_id', 'user_id', 'city_id', 'city', 'total', 'promo_code', 'promo_discount', 'final_total', 'payment_method', 'admin_earnings', 'visiting_charges', 'partner_earnings', 'address_id', 'address', 'date_of_service', 'starting_time', 'ending_time', 'duration', 'status', 'status_changed_by_type', 'status_changed_by_id', 'remarks', 'payment_status', 'otp', 'isRefunded', 'payment_status_of_additional_charge', 'additional_charges', 'total_additional_charge', 'custom_job_request_id', 'payment_method_of_additional_charge'];

    protected function getAdditionalChargeTaxAmount(?string $additionalCharges): float
    {
        $charges = !empty($additionalCharges) ? json_decode($additionalCharges, true) : [];
        if (!is_array($charges)) {
            return 0;
        }

        return round(array_sum(array_map(static function ($charge) {
            return (float) ($charge['tax_amount'] ?? 0);
        }, $charges)), 2);
    }
    public function __construct()
    {
        parent::__construct();

        // Reuse a single IonAuth instance for the whole model.
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->permissionService = new PermissionService();

        // Determine admin_id and isAdminPanel.
        // A user is "admin panel" if they're in the IonAuth admin group
        // OR if the PermissionService recognises them as a super-admin.
        // This fixes the bug where super-admins got treated as partners.
        if ($this->ionAuth->loggedIn()) {
            $userId = (int) $this->ionAuth->user()->row()->id;
            $this->isAdminPanel = $this->ionAuth->isAdmin()
                || $this->permissionService->isSuperAdmin($userId);
            $this->admin_id = $this->isAdminPanel ? $userId : 0;
        }
    }
    /**
     * COD orders never get a transactions row (place_order() only inserts one for
     * online gateways), so transactions.status is always null for them. COD payment
     * state instead lives on orders.status: cash is only collected on completion.
     */
    public function getPaymentStatus(?string $paymentMethod, ?string $orderStatus, ?string $transactionStatus): string
    {
        if ($paymentMethod === 'cod') {
            return $orderStatus === 'completed' ? 'success' : 'pending';
        }
        return $transactionStatus ?: 'pending';
    }

    /**
     * Sum tax from all order_services rows for an order (tax_amount is stored per unit).
     * Mirrors the aggregation used by list()/custom_booking_list() for the list-level tax_amount field.
     */
    public function getTotalTaxAmount(int $orderId): float
    {
        $row = $this->db->table('order_services')
            ->select('SUM(tax_amount * quantity) as tax_amount', false)
            ->where('order_id', $orderId)
            ->get()
            ->getRowArray();

        return (float) ($row['tax_amount'] ?? 0);
    }

    /**
     * tax_type ('included'/'excluded') is stored per order_services row at order time,
     * not at order level — all rows of one order share the same value, so read the first.
     */
    public function getOrderTaxType(int $orderId): string
    {
        $row = $this->db->table('order_services')
            ->select('tax_type')
            ->where('order_id', $orderId)
            ->get()
            ->getRowArray();

        return $row['tax_type'] ?? 'included';
    }

    /**
     * Slim service list for handyman booking details: id, image, translated title,
     * quantity, and unit price as stored on order_services at order time (no tax/discount noise).
     */
    public function getBookingServices(int $orderId): array
    {
        $fileService = service('fileService');

        $rows = $this->db->table('order_services os')
            ->select('os.service_id, os.service_title, os.service_title_translations, os.quantity, os.price, s.image')
            ->join('services s', 's.id = os.service_id', 'left')
            ->where('os.order_id', $orderId)
            ->get()
            ->getResultArray();

        $services = [];
        $currentLanguage = (service('request')->getHeaderLine('Content-Language')) ? $this->getCurrentLanguageFromRequest() : get_current_language();
        
        foreach ($rows as $row) {
            $translatedTitle = $this->resolveServiceTitleFromSnapshot($row, $currentLanguage);
            $services[] = [
                'service_id' => (int) $row['service_id'],
                'image' => $fileService->url($row['image'], 'services', 'public/backend/assets/default.png'),
                'title' => $translatedTitle ?: $row['service_title'],
                'quantity' => (int) $row['quantity'],
                'price' => (float) $row['price'],
            ];
        }

        return $services;
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $where_in_key = '', $where_in_value = [], $addition_data = '', $download_invoice = false, $newUI = false, $is_provider = false)
    {
        $fileService = service('fileService');
        $db = \Config\Database::connect();
        $sessionEmail = $_SESSION['email'] ?? '';
        $isMasked = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $sessionEmail != 'superadmin@gmail.com';
        $maskPhone = function ($value) {
            return strlen($value) > 6 ? 'XXXXX' . substr($value, 6) : 'XXXXX';
        };
        $maskEmail = function ($value) {
            return strlen($value) > 6 ? 'wrteam.' . substr($value, 6) : 'wrteam.***';
        };

        if ($newUI == true || $newUI == 1) {
            $db = \Config\Database::connect();
            $builder = $db->table('orders o');
            $multipleWhere = [];
            $bulkData = $rows = $tempRow = [];
            if (isset($_GET['limit'])) {
                $limit = $_GET['limit'];
            }
            if (isset($_GET['sort'])) {
                if ($_GET['sort'] == 'o.id') {
                    $sort = "o.id";
                } else {
                    $sort = $_GET['sort'];
                }
            }
            if (isset($_GET['order'])) {
                $order = $_GET['order'];
            }
            if (isset($_GET['offset']))
                $offset = $_GET['offset'];
            if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
                $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
                $multipleWhere = [
                    '`o.id`' => $search,
                    '`o.user_id`' => $search,
                    '`o.partner_id`' => $search,
                    '`o.total`' => $search,
                    '`o.address`' => $search,
                    '`o.date_of_service`' => $search,
                    '`o.starting_time`' => $search,
                    '`o.ending_time`' => $search,
                    '`o.duration`' => $search,
                    '`o.status`' => $search,
                    '`o.remarks`' => $search,
                    '`up.username`' => $search,
                    '`u.username`' => $search,
                    '`os.service_title`' => $search,
                    '`os.status`' => $search,
                ];
            }
            $order_count = $builder->select('count(DISTINCT(o.id)) as total')
                ->join('order_services os', 'os.order_id=o.id')
                ->join('users u', 'u.id=o.user_id')
                ->join('users up', 'up.id=o.partner_id')
                ->join('partner_details pd', 'o.partner_id = pd.partner_id');
            if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
                $builder->where('o.status', $_GET['order_status_filter']);
            }
            if (isset($_GET['final_total_filter']) && $_GET['final_total_filter'] != '') {
                $builder->where('o.final_total', $_GET['final_total_filter']);
            }
            if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
                $builder->where('o.date_of_service', $_GET['filter_date']);
            }
            if (isset($where) && !empty($where)) {
                $builder->where($where);
            }
            if (!empty($where_in_key) && !empty($where_in_value)) {
                $builder->whereIn($where_in_key, $where_in_value);
            }
            if (!empty($multipleWhere)) {
                $builder->groupStart();
                $builder->orLike($multipleWhere);
                $builder->groupEnd();
            }

            $order_count = $builder->get()->getResultArray();
            $total = $order_count[0]['total'];

            $builder->select('o.*,t.status as payment_status,o.order_latitude as order_latitude,o.order_longitude as order_longitude ,pd.advance_booking_days,u.id as customer_id,u.username as user_name,u.image as user_image,u.phone as customer_no,u.latitude as 	latitude,u.longitude as longitude  ,up.image as provider_profile_image,u.email as customer_email,up.username as partner_name,up.phone as partner_no,u.balance as user_wallet, pd.company_name,o.visiting_charges,pd.address as partner_address')
                ->join('order_services os', 'os.order_id=o.id')
                ->join('users u', 'u.id=o.user_id')
                ->join('addresses a', 'a.id=o.address_id', 'left')
                ->join('users up', 'up.id=o.partner_id')
                ->join('partner_details pd', 'o.partner_id = pd.partner_id')
                ->join('transactions t', 't.order_id = o.id', 'left');
            if (isset($_GET['limit'])) {
                $limit = $_GET['limit'];
            }
            if (isset($_GET['sort'])) {
                if ($_GET['sort'] == 'o.id') {
                    $sort = "o.id";
                } else if ($_GET['sort'] == 'customer') {
                    $sort = "u.id";
                } else {
                    $sort = $_GET['sort'];
                }
            }
            if (isset($_GET['order'])) {
                $order = $_GET['order'];
            }
            if (isset($_GET['offset']))
                $offset = $_GET['offset'];
            if (isset($where) && !empty($where)) {
                $builder->where($where);
            }
            if (!empty($where_in_key) && !empty($where_in_value)) {
                $builder->whereIn($where_in_key, $where_in_value);
            }
            if (isset($multipleWhere) && !empty($multipleWhere)) {
                $builder->groupStart();
                $builder->orLike($multipleWhere);
                $builder->groupEnd();
            }
            if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
                $builder->where('o.status', $_GET['order_status_filter']);
            }
            if (isset($_GET['final_total_filter']) && $_GET['final_total_filter'] != '') {
                $builder->where('o.final_total', $_GET['final_total_filter']);
            }
            if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
                $builder->where('o.date_of_service', $_GET['filter_date']);
            }
            if (isset($_POST['status']) && $_POST['status'] != '') {
                $builder->where('o.status', $_POST['status']);
            }
            $order_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();

            $bulkData = array();
            $bulkData['total'] = $total;
            $rows = array();
            $tempRow = array();
            if (empty($order_record)) {
                $bulkData = array();
            } else {
                foreach ($order_record as $row) {
                    $builder = $db->table('order_services os');
                    $services = $builder->select('
                    os.id,
                    os.order_id,
                    os.service_id,
                    os.service_title,
                    os.service_title_translations,
                    os.tax_percentage,
                    os.discount_price,
                    os.tax_amount,
                    os.price,
                    os.quantity,
                    os.sub_total,
                    os.status,                
                    ,os.tax_type,s.tags,s.duration,s.category_id,s.is_cancelable,s.cancelable_till,s.title,s.tax_id,s.image,sr.rating,sr.comment,sr.images')
                        ->where('os.order_id', $row['id'])
                        ->join('services as s', 's.id=os.service_id', 'left')
                        ->join('services_ratings as sr', 'sr.service_id=os.service_id AND sr.user_id=' . $row["user_id"] . '', 'left')->get()->getResultArray();
                    $order_record['order_services'] = $services;
                    foreach ($order_record['order_services'] as $key => $os) {
                        $taxPercentageData = fetch_details('taxes', ['id' => $os['tax_id']], ['percentage']);
                        if (!empty($taxPercentageData)) {
                            $taxPercentage = $taxPercentageData[0]['percentage'];
                        } else {
                            $taxPercentage = 0;
                        }
                        // Tax value must always be present (included = tax inside price, excluded = tax on top)
                        if ($os['discount_price'] == "0") {
                            if ($os['tax_type'] == "excluded") {
                                $order_record['order_services'][$key]['price_with_tax'] = (str_replace(',', '', number_format(strval($os['price'] + ($os['price'] * ($taxPercentage) / 100)), 2)));
                                $order_record['order_services'][$key]['tax_value'] = (str_replace(',', '', number_format(((($os['price'] * ($taxPercentage) / 100))), 2)));
                                $order_record['order_services'][$key]['original_price_with_tax'] = (str_replace(',', '', number_format(strval($os['price'] + ($os['price'] * ($taxPercentage) / 100)), 2)));
                            } else {
                                $taxVal = calculate_tax_amount((float) $os['price'], (float) $taxPercentage, 'included');
                                $order_record['order_services'][$key]['price_with_tax'] = (str_replace(',', '', number_format(strval($os['price']), 2)));
                                $order_record['order_services'][$key]['tax_value'] = str_replace(',', '', number_format($taxVal, 2));
                                $order_record['order_services'][$key]['original_price_with_tax'] = (str_replace(',', '', number_format(strval($os['price']), 2)));
                            }
                        } else {
                            if ($os['tax_type'] == "excluded") {
                                $order_record['order_services'][$key]['price_with_tax'] = (str_replace(',', '', number_format(strval($os['discount_price'] + ($os['discount_price'] * ($taxPercentage) / 100)), 2)));
                                $order_record['order_services'][$key]['tax_value'] = number_format(((($os['discount_price'] * ($taxPercentage) / 100))), 2);
                                $order_record['order_services'][$key]['original_price_with_tax'] = (str_replace(',', '', number_format(strval($os['price'] + ($os['price'] * ($taxPercentage) / 100)), 2)));
                            } else {
                                $taxVal = calculate_tax_amount((float) $os['discount_price'], (float) $taxPercentage, 'included');
                                $order_record['order_services'][$key]['price_with_tax'] = (str_replace(',', '', number_format(strval($os['discount_price']), 2)));
                                $order_record['order_services'][$key]['tax_value'] = number_format($taxVal, 2);
                                $order_record['order_services'][$key]['original_price_with_tax'] = (str_replace(',', '', number_format(strval($os['price']), 2)));
                            }
                        }

                        // Fix title field: if title is empty, use service_title as fallback
                        if (empty($order_record['order_services'][$key]['title'])) {
                            $order_record['order_services'][$key]['title'] = $order_record['order_services'][$key]['service_title'] ?? '';
                        }
                    }
                    if ($from_app == false) {
                        $operations = '<a href="' . ltrim(site_url('partner/orders/veiw_orders/' . $row['id']), '/') . '" class="btn  btn-sm action-button p-2" title="' . labels('view_the_order', 'View the Order') . '"><o class="material-symbols-outlined">
                        more_vert
                        </o> </a>';
                        if (($row['status'] == 'awaiting')) {
                            $status = " <div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('awaiting', 'Awaiting') .
                                "</div>";
                        } elseif (($row['status'] == 'confirmed')) {
                            $status = " <div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2  bg-emerald-purple text-emerald-purple dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('confirmed', 'Confirmed') . "
                            </div>";
                        } elseif (($row['status'] == 'rescheduled')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('rescheduled', 'Rescheduled') . "
                            </div>";
                        } elseif (($row['status'] == 'cancelled')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('cancelled', 'Cancelled') . "
                            </div>";
                        } elseif (($row['status'] == 'completed')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('completed', 'Completed') . "
                            </div>";
                        } elseif (($row['status'] == 'started')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2  bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('started', 'Started') . "
                            </div>";
                        } elseif (($row['status'] == 'pending')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2  bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('pending', 'Pending') . "
                            </div>";
                        } elseif (($row['status'] == 'on_the_way')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('on_the_way', 'On The Way') . "
                            </div>";
                        } elseif (($row['status'] == 'arrived')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-purple text-emerald-purple dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('arrived', 'Arrived') . "
                            </div>";
                        } elseif (($row['status'] == 'booking_ended')) {
                            $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2  bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('booking_ended', 'Booking Ended') . "
                            </div>";
                        } else {
                            $status = labels('undefined_status', 'Status Not Defined');
                        }
                    } else {
                        $status = $row['status'];
                    }
                    $tax_amount = 0;
                    foreach ($order_record['order_services'] as $order_data) {
                        $tax_amount = ($order_data['tax_type'] == "excluded") ? number_format($tax_amount, 2) + ((($order_data['tax_amount'])) * $order_data['quantity']) : number_format($tax_amount, 2);
                    }
                    $s = [];
                    foreach ($order_record['order_services'] as $service_data) {
                        $array_ids = fetch_details('services s', ['id' => $service_data['service_id']], 'is_cancelable');
                        foreach ($array_ids as $ids) {
                            array_push($s, $ids['is_cancelable']);
                        }
                    }
                    if ($is_provider == true) {
                        $row['user_image'] = !empty($row['user_image'])
                            ? $fileService->url(basename($row['user_image']), 'profile', 'public/uploads/profiles/default.png')
                            : base_url('public/uploads/profiles/default.png');
                    } else {
                        $row['provider_profile_image'] = !empty($row['provider_profile_image'])
                            ? $fileService->url(basename($row['provider_profile_image']), 'profile', 'public/uploads/profiles/default.png')
                            : base_url('public/uploads/profiles/default.png');
                    }
                    $tempRow['id'] = $row['id'];

                    $tempRow['isRefunded'] = $row['isRefunded'];
                    $tempRow['customer'] = $row['user_name'];
                    $tempRow['customer_id'] = $row['customer_id'];

                    $tempRow['customer_latitude'] = $row['latitude'];
                    $tempRow['customer_longitude'] = $row['longitude'];
                    $tempRow['latitude'] = $row['order_latitude'];
                    $tempRow['longitude'] = $row['order_longitude'];
                    $tempRow['advance_booking_days'] = $row['advance_booking_days'];
                    $tempRow['customer_no'] = $isMasked ? $maskPhone($row['customer_no']) : $row['customer_no'];
                    $tempRow['customer_email'] = $isMasked ? $maskEmail($row['customer_email']) : $row['customer_email'];
                    $tempRow['user_wallet'] = $row['user_wallet'];
                    $tempRow['payment_method'] = $row['payment_method'];
                    $tempRow['payment_status'] = $row['payment_status'];

                    // Get provider name with language fallback: current language → default language → base table
                    // Priority: current language translation → default language translation → base table company_name
                    $providerName = $row['partner_name']; // Default fallback to partner_name from query
                    if (!empty($row['partner_id'])) {
                        $currentLang = get_current_language();
                        $defaultLang = get_default_language();

                        // Get translated partner details
                        $translatedPartnerModel = new \App\Models\TranslatedPartnerDetails_model();
                        $allTranslations = $translatedPartnerModel->getAllTranslationsForPartner($row['partner_id']);

                        if (!empty($allTranslations)) {
                            $currentTranslation = null;
                            $defaultTranslation = null;

                            // Organize translations by language code
                            $translationsByLang = [];
                            foreach ($allTranslations as $translation) {
                                $translationsByLang[$translation['language_code']] = $translation;
                            }

                            // Try current language first
                            if (!empty($translationsByLang[$currentLang]['company_name'])) {
                                $providerName = $translationsByLang[$currentLang]['company_name'];
                            } elseif (!empty($translationsByLang[$defaultLang]['company_name'])) {
                                // Fallback to default language
                                $providerName = $translationsByLang[$defaultLang]['company_name'];
                            } elseif (!empty($row['company_name'])) {
                                // Final fallback to base table
                                $providerName = $row['company_name'];
                            }
                        } elseif (!empty($row['company_name'])) {
                            // If no translations exist, use base table company_name
                            $providerName = $row['company_name'];
                        }
                    }
                    $tempRow['partner'] = $providerName;
                    $tempRow['profile_image'] = ($is_provider == true) ? ($row['provider_profile_image'] ?? '') : ($row['user_image'] ?? '');
                    $tempRow['user_id'] = $row['user_id'];
                    $tempRow['partner_id'] = $row['partner_id'];
                    $tempRow['city_id'] = $row['city'];
                    $tempRow['total'] = (str_replace(',', '', number_format($row['total'], 2)));
                    $tempRow['tax_amount'] = strval(number_format($tax_amount, 2));
                    $tempRow['promo_code'] = $row['promo_code'];
                    $tempRow['promo_discount'] = $row['promo_discount'];
                    $tempRow['final_total'] = ceil(str_replace(',', '', $row['final_total']));
                    $tempRow['admin_earnings'] = $row['admin_earnings'];
                    $tempRow['partner_earnings'] = $row['partner_earnings'];
                    $tempRow['address_id'] = $row['address_id'];
                    // Remove empty values between commas
                    $cleaned_address = preg_replace('/,+/', ',', $row['address']);  // Replaces multiple commas with a single comma

                    // Remove leading and trailing commas (if any)
                    $cleaned_address = trim($cleaned_address, ',');
                    $tempRow['address'] = $cleaned_address;
                    $tempRow['date_of_service'] = date("d-M-Y", strtotime($row['date_of_service']));
                    $tempRow['starting_time'] = date("h:i A", strtotime($row['starting_time']));
                    $tempRow['ending_time'] = date("h:i A", strtotime($row['ending_time']));
                    $tempRow['duration'] = $row['duration'];
                    $tempRow['partner_address'] = $row['partner_address'];
                    $tempRow['partner_no'] = $isMasked ? $maskPhone($row['partner_no']) : $row['partner_no'];
                    $tempRow['service_image'] = "frg";
                    if (in_array(0, $s)) {
                        $tempRow['is_cancelable'] = 0;
                    } else {
                        $order_date = strtotime($order_record[0]['date_of_service']);
                        $start_time = strtotime($order_record[0]['starting_time']);
                        $cancellation_window = (intval($order_record['order_services'][0]['cancelable_till']));
                        $order_timestamp = strtotime(date('Y-m-d', $order_date) . ' ' . date('H:i:s', $start_time));
                        $cancellation_time = $order_timestamp - ($cancellation_window * 60);
                        $current_time = time();
                        if ($current_time <= $cancellation_time) {
                            $tempRow['is_cancelable'] = 1;
                        } else {
                            $tempRow['is_cancelable'] = 0;
                        }
                    }
                    $tempRow['status'] = $status;
                    $tempRow['remarks'] = $row['remarks'];
                    $tempRow['created_at'] = date("d-M-Y h:i A", strtotime($row['created_at']));
                    $tempRow['company_name'] = $row['company_name'];
                    $tempRow['visiting_charges'] = (str_replace(',', '', number_format($row['visiting_charges'], 2)));
                    $tempRow['services'] = $order_record['order_services'];

                    // Apply translations to the order data if this is an API call
                    if ($from_app) {
                        $languageCode = $this->getCurrentLanguageFromRequest();
                        $tempRow = $this->applyTranslationsToOrder($tempRow, $languageCode);
                        $tempRow['translated_status'] = getTranslatedValue($row['status'], 'panel');
                    }

                    $tempRow['invoice_no'] = 'INV-' . $row['id'];
                    $tempRow['slug'] = 'inv-' . $row['id'];
                    if (!$from_app) {
                        $tempRow['operations'] = $operations;
                        unset($tempRow['updated_at']);
                    }
                    // print_r($tempRow); die;
                    $rows[] = $tempRow;
                }
            }
            // Rebuild address strings from custom fields.
            Addresses_model::rebuildAddressOnOrderRows($rows);

            $bulkData['rows'] = $rows;
            if ($from_app) {
                $data['total'] = $total;
                $data['data'] = $rows;
                return $data;
            } else {
                return json_encode($bulkData);
            }
        }
        $builder = $db->table('orders o');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'o.id') {
                $sort = "o.id";
            } else if ($_GET['sort'] == 'customer') {
                $sort = "u.id";
            } else if ($sort == 'invoice_no') {
                $sort = "o.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`o.id`' => $search,
                '`o.user_id`' => $search,
                '`o.partner_id`' => $search,
                '`o.total`' => $search,
                '`o.address`' => $search,
                '`o.date_of_service`' => $search,
                '`o.starting_time`' => $search,
                '`o.ending_time`' => $search,
                '`o.duration`' => $search,
                '`o.status`' => $search,
                '`o.remarks`' => $search,
                '`up.username`' => $search,
                '`u.username`' => $search,
                '`os.service_title`' => $search,
                '`os.status`' => $search,
            ];
        }
        $order_count = $builder->select('count(DISTINCT(o.id)) as total')
            ->join('order_services os', 'os.order_id=o.id')
            ->join('users u', 'u.id=o.user_id')
            ->join('users up', 'up.id=o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id');
        if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
            $builder->where('o.created_at', $_GET['filter_date']);
        }
        if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
            $builder->where('o.status', $_GET['order_status_filter']);
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            $sortFieldMap = [
                'id' => 'o.id',
                'o.id' => 'o.id',
                'invoice_no' => 'o.id',
                'user_id' => 'o.user_id',
                'customer' => 'u.id',
                'partner' => 'up.id',
                'city_id' => 'o.city_id',
                'total' => 'o.total',
                'promo_code' => 'o.promo_code',
                'promo_discount' => 'o.promo_discount',
                'final_total' => 'o.final_total',
                'admin_earnings' => 'o.admin_earnings',
                'partner_earnings' => 'o.partner_earnings',
                'date_of_service' => 'o.date_of_service',
                'new_start_time_with_date' => 'o.starting_time',
                'new_end_time_with_date' => 'o.ending_time',
                'duration' => 'o.duration',
                'status' => 'o.status',
            ];
            $sort = $sortFieldMap[$_GET['sort']] ?? (strpos($_GET['sort'], '.') !== false ? $_GET['sort'] : 'o.id');
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (!empty($where_in_key) && !empty($where_in_value)) {
            $builder->whereIn($where_in_key, $where_in_value);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $order_count = $builder->get()->getResultArray();

        $total = $order_count[0]['total'];
        $builder
            ->select('o.*, t.status as payment_status, pd.advance_booking_days, up.email as partner_email, up.phone as partner_phone,
            u.id as customer_id, u.username as user_name, u.image as user_image, u.phone as customer_no, u.country_code as customer_country_code,
            u.latitude as latitude, u.longitude as longitude, partner_subscriptions.name as subscription_name,partner_subscriptions.id as partner_subscription_id,partner_subscriptions.status as subscription_status,
            up.image as provider_profile_image, u.email as customer_email, up.username as partner_name,pd.chat as post_booking_chat, pd.pre_chat as pre_booking_chat,
            up.phone as partner_no, up.country_code as partner_country_code, u.balance as user_wallet, up.latitude as partner_latitude,
            up.longitude as partner_longitude, pd.company_name, o.visiting_charges, pd.address as partner_address,u.payable_commision')
            ->join('order_services os', 'os.order_id = o.id')
            ->join('users u', 'u.id = o.user_id')
            ->join('users up', 'up.id = o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id')
            ->join('(SELECT partner_id, MAX(created_at) AS latest_subscription_date 
                FROM partner_subscriptions 
                GROUP BY partner_id) latest_subscriptions', 'latest_subscriptions.partner_id = pd.partner_id')
            ->join('partner_subscriptions', 'partner_subscriptions.partner_id = latest_subscriptions.partner_id AND partner_subscriptions.created_at = latest_subscriptions.latest_subscription_date', 'left')
            ->join('transactions t', 't.order_id = o.id', 'left');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (!empty($where_in_key) && !empty($where_in_value)) {
            $builder->whereIn($where_in_key, $where_in_value);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
            $builder->where('o.status', $_GET['order_status_filter']);
        }
        if (isset($_GET['order_provider_filter']) && $_GET['order_provider_filter'] != '') {
            $builder->where('o.partner_id', $_GET['order_provider_filter']);
        }
        if (isset($_POST['status']) && $_POST['status'] != '') {
            $builder->where('o.status', $_POST['status']);
        }
        $builder->where('o.parent_id', null);
        $order_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $check_payment_gateway = get_settings('payment_gateways_settings', true);
        if (empty($order_record)) {
            $bulkData = array();
        } else {
            if ($from_app == false) {
                $permissions = $this->permissionService->permissionsFor($this->admin_id);
            }

            foreach ($order_record as $index_for_order => $row) {
                $builder = $db->table('order_services os');
                if ($row['custom_job_request_id'] != NULL || $row['custom_job_request_id'] != "") {
                    $services = $builder->select('
                    os.id,
                    os.order_id,
                    os.service_title,
                    os.service_title_translations,
                    os.tax_percentage,
                    os.discount_price,
                    os.tax_amount,
                    os.price,
                    os.quantity,
                    os.sub_total,
                    os.custom_job_request_id,
                    os.status,
                    cjr.service_title as job_title,
                    cjr.category_id,
                    cjr.min_price,
                    cjr.max_price,
                    cjr.requested_start_date,
                    cjr.requested_end_date,
                    MAX(pb.counter_price) as counter_price,
                    MAX(pb.duration) as duration,
                    pb.tax_id,
                    cjr.service_short_description,
                    pb.note,
                    MAX(pb.tax_amount) as tax_amount,
                    MAX(pb.tax_percentage) as tax_percentage,
                    sr.rating,
                    sr.comment,
                    sr.images')
                        ->where('os.order_id', $row['id'])
                        ->join('custom_job_requests as cjr', 'cjr.id=os.custom_job_request_id', 'left')
                        ->join('partner_bids as pb', 'pb.custom_job_request_id=os.custom_job_request_id', 'left')
                        ->join('services_ratings as sr', 'sr.custom_job_request_id = os.custom_job_request_id AND sr.user_id=' . $row["user_id"], 'left')
                        ->groupBy('os.id') // Group by primary key or unique identifier
                        ->get()
                        ->getResultArray();
                } else {
                    // Query for regular service
                    $services = $builder->select('
                                os.id,
                                os.order_id,
                                os.service_id,
                                os.service_title,
                                os.service_title_translations,
                                os.tax_percentage,
                                os.discount_price,
                                os.tax_amount,
                                os.price,
                                os.quantity,
                                os.sub_total,
                                os.status,              
                                s.tags, s.duration, s.category_id, s.is_cancelable, s.cancelable_till,
                                s.title, os.tax_type, s.tax_id, s.image,
                                sr.rating, sr.comment, sr.images,')
                        ->where('os.order_id', $row['id'])
                        ->join('services as s', 's.id=os.service_id', 'left')
                        ->join('services_ratings as sr', 'sr.service_id=os.service_id AND sr.user_id=' . $row["user_id"], 'left')
                        ->get()->getResultArray();
                }
                $order_record['order_services'] = $services;
                foreach ($order_record['order_services'] as $key => $os) {

                    $taxPercentageData = fetch_details('taxes', ['id' => $os['tax_id']], ['percentage']);
                    $taxPercentage = !empty($taxPercentageData) ? (float) $taxPercentageData[0]['percentage'] : 0;

                    $isCustomJob = !empty($row['custom_job_request_id']);
                    // Use tax_type from order_services (stored at order time), not from services table
                    $isTaxExcluded = (!empty($os['tax_type']) && $os['tax_type'] === 'excluded');

                    $price = (float) $os['price'];
                    $discountPrice = (float) $os['discount_price'];
                    $basePrice = ($discountPrice > 0 && !$isCustomJob) ? $discountPrice : $price;

                    // Tax value must always be present (included = extract from price, excluded = on top)
                    $taxValue = ($isTaxExcluded || $isCustomJob)
                        ? ($basePrice * $taxPercentage / 100)
                        : calculate_tax_amount($basePrice, $taxPercentage, 'included');

                    $priceWithTax = ($isTaxExcluded || $isCustomJob)
                        ? $basePrice + ($basePrice * $taxPercentage / 100)
                        : $basePrice;

                    /**
                     * COMMON ASSIGNMENTS (single source of truth)
                     * tax_type comes from order_services table only.
                     */
                    $order_record['order_services'][$key]['price_with_tax']
                        = strval(number_format($priceWithTax, 2, '.', ''));

                    $order_record['order_services'][$key]['tax_value']
                        = strval(number_format($taxValue, 2, '.', ''));

                    // Use stored order_services.tax_type; fallback to 'included' for legacy rows
                    $order_record['order_services'][$key]['tax_type']
                        = !empty($os['tax_type']) ? $os['tax_type'] : 'included';

                    /**
                     * ORIGINAL PRICE (no discount) — tax value for display only
                     */
                    $originalTax = ($isTaxExcluded || $isCustomJob)
                        ? ($price * $taxPercentage / 100)
                        : calculate_tax_amount($price, $taxPercentage, 'included');

                    $order_record['order_services'][$key]['original_price_with_tax']
                        = strval(number_format(($isTaxExcluded || $isCustomJob) ? ($price + $originalTax) : $price, 2, '.', ''));

                    /**
                     * CUSTOM JOB EXTRAS
                     */
                    if ($isCustomJob) {
                        $order_record['order_services'][$key]['service_short_description'] = $os['service_short_description'];
                        $order_record['order_services'][$key]['note'] = $os['note'];
                    }

                    // Handle service image with proper fallback logic
                    // Default image should only be shown if:
                    // 1. Image doesn't exist in database AND server
                    // 2. Image exists in database but file doesn't exist on server
                    if ($row['custom_job_request_id'] != NULL || $row['custom_job_request_id'] != "") {
                        // Custom job requests always use default image
                        $order_record['order_services'][$key]['image'] = base_url("public/uploads/profiles/default.png");
                    } else {
                        // Use get_file_url() similar to promocodes implementation
                        // This function checks if file exists and returns default if missing
                        if (!empty($os['image'])) {
                            // Build the full file path for the service image
                            // Check if image path already includes 'public/uploads/services/' to avoid duplication
                            if (strpos($os['image'], 'public/uploads/services/') !== false) {
                                $image_path = $os['image'];
                            } else {
                                $image_path = 'public/uploads/services/' . $os['image'];
                            }
                            $order_record['order_services'][$key]['image'] = $fileService->url($image_path, 'services', 'public/uploads/profiles/default.png');
                        } else {
                            // If no image path in database, show default image
                            $order_record['order_services'][$key]['image'] = base_url('public/uploads/profiles/default.png');
                        }
                    }
                    if (empty($os['images'])) {
                        $os['images'] = [];
                    } else {
                        $image_paths = json_decode($os['images'], true);
                        if ($image_paths !== null) {
                            $updated_images = [];
                            foreach ($image_paths as $path) {
                                $updated_images[] = $fileService->url($path, 'ratings');
                            }
                            $os['images'] = $updated_images;
                        } else {
                            $os['images'] = [];
                        }
                    }
                    $order_record['order_services'][$key]['images'] = $os['images'];

                    // Fix title field: if title is empty, use service_title as fallback
                    if (empty($order_record['order_services'][$key]['title'])) {
                        $order_record['order_services'][$key]['title'] = $order_record['order_services'][$key]['service_title'] ?? '';
                    }
                }
                $operations = '';
                if ($from_app == false) {
                    // Build the operations dropdown via the helper.
                    // Uses $this->isAdminPanel (set once in constructor) so
                    // super-admins are handled the same as regular admins.
                    $operations = $this->buildOperationsHtml($row);
                    if (($row['status'] == 'awaiting')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('awaiting', 'Awaiting') . " 
                        </div>";
                    } elseif (($row['status'] == 'confirmed')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('confirmed', 'Confirmed') . "
                        </div>";
                    } elseif (($row['status'] == 'rescheduled')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('rescheduled', 'Rescheduled') . "
                        </div>";
                    } elseif (($row['status'] == 'cancelled')) {
                        $status = " <div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('cancelled', 'Cancelled') . "
                        </div>";
                    } elseif (($row['status'] == 'completed')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('completed', 'Completed') . "
                        </div>";
                    } elseif (($row['status'] == 'pending')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('pending', 'Pending') . "
                        </div>";
                    } elseif (($row['status'] == 'started')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('started', 'Started') . "
                        </div>";
                    } elseif (($row['status'] == 'on_the_way')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('on_the_way', 'On The Way') . "
                        </div>";
                    } elseif (($row['status'] == 'arrived')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-purple text-emerald-purple dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('arrived', 'Arrived') . "
                        </div>";
                    } elseif (($row['status'] == 'booking_ended')) {
                        $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('booking_ended', 'Booking Ended') . "
                        </div>";
                    } else {
                        $status = labels('undefined_status', 'Status Not Defined');
                    }
                } else {
                    $status = $row['status'];
                }
                // Sum tax from all order services regardless of excluded/included tax type
                $tax_amount = 0;
                foreach ($order_record['order_services'] as $order_data) {
                    $tax_amount += ($order_data['tax_amount'] * $order_data['quantity']);
                }
                $tax_amount += $this->getAdditionalChargeTaxAmount($row['additional_charges'] ?? null);
                $s = [];
                foreach ($order_record['order_services'] as $service_data) {
                    if (isset($service_data['custom_job_request_id']) && ($service_data['custom_job_request_id'] != NULL || $service_data['custom_job_request_id'] != "")) {
                        $array_ids = [];
                    } else {
                        $array_ids = fetch_details('services s', ['id' => $service_data['service_id']], 'is_cancelable');
                        foreach ($array_ids as $ids) {
                            array_push($s, $ids['is_cancelable']);
                        }
                    }
                }
                $row['user_image'] = !empty($row['user_image'])
                    ? $fileService->url($row['user_image'], 'profile')
                    : null;
                $row['provider_profile_image'] = !empty($row['provider_profile_image'])
                    ? $fileService->url($row['provider_profile_image'], 'profile', 'public/backend/assets/default.png')
                    : base_url('public/backend/assets/default.png');

                $tempRow['id'] = $row['id'];
                $tempRow['slug'] = 'inv-' . $row['id'];
                $tempRow['customer'] = $row['user_name'];
                $tempRow['customer_id'] = $row['customer_id'];
                $tempRow['profile_image'] = ($is_provider) ? ($row['user_image'] ?? '') : ($row['provider_profile_image'] ?? '');
                $tempRow['latitude'] = $row['order_latitude'];
                $tempRow['longitude'] = $row['order_longitude'];
                $tempRow['partner_latitude'] = $row['partner_latitude'];
                $tempRow['partner_longitude'] = $row['partner_longitude'];
                $tempRow['advance_booking_days'] = $row['advance_booking_days'];
                $tempRow['customer_no'] = $isMasked ? $maskPhone($row['customer_no']) : $row['customer_no'];
                $tempRow['customer_country_code'] = $isMasked ? '' : ($row['customer_country_code'] ?? '');
                $tempRow['customer_email'] = $isMasked ? $maskEmail($row['customer_email']) : $row['customer_email'];
                $tempRow['user_wallet'] = $row['user_wallet'];
                $tempRow['payment_method'] = $row['payment_method'];
                $tempRow['payment_status'] = $row['payment_status'];

                // Get provider name with language fallback: current language → default language → base table
                // Priority: current language translation → default language translation → base table company_name
                $providerName = $row['partner_name'] ?? ''; // Default fallback to partner_name from query
                $companyName = $partner_details[0]['company_name'] ?? ''; // fallback to base table
                if (!empty($row['partner_id'])) {
                    $currentLang = get_current_language();
                    $defaultLang = get_default_language();

                    // Get translated partner details
                    $translatedPartnerModel = new \App\Models\TranslatedPartnerDetails_model();
                    $allTranslations = $translatedPartnerModel->getAllTranslationsForPartner($row['partner_id']);

                    if (!empty($allTranslations)) {
                        $currentTranslation = null;
                        $defaultTranslation = null;

                        // Organize translations by language code
                        $translationsByLang = [];
                        foreach ($allTranslations as $translation) {
                            $translationsByLang[$translation['language_code']] = $translation;
                        }

                        // Try current language first
                        if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['username'])) {
                            $providerName = $translationsByLang[$currentLang]['username'];
                        } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['username'])) {
                            $providerName = $translationsByLang[$defaultLang]['username'];
                        }

                        // Try current language first
                        if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                            $companyName = $translationsByLang[$currentLang]['company_name'];
                        }
                        // Fallback to default language
                        elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                            $companyName = $translationsByLang[$defaultLang]['company_name'];
                        }
                    } elseif (!empty($row['company_name'])) {
                        // If no translations exist, use base table company_name
                        $companyName = $row['company_name'];
                    }
                }

                // echo "<pre>";
                // print_r($row);
                // die();
                $imageSrc = $fileService->url($row['provider_profile_image'], 'profile', 'public/backend/assets/default.png');

                $profile = '<div class="o-media o-media--middle">
                            <a href="' . $imageSrc . '" data-lightbox="image-1">
                                <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $providerName . '">
                            </a>';
                $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                    <div class="provider_name_table" >' . $providerName . '</span></div>
                    <div class="provider_email_table">' . $companyName . '</div>
                    <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                    </div>
                    </div></a>';

                $finalTotal = round($order_record['order_services'][0]['sub_total'] + $row['visiting_charges'], 2);

                $tempRow['partner'] = $profile;
                $tempRow['user_id'] = $row['user_id'];
                $tempRow['partner_id'] = $row['partner_id'];
                $tempRow['city_id'] = $row['city'];
                $tempRow['total'] = (str_replace(',', '', $row['total']));
                $tempRow['tax_amount'] = strval($tax_amount);
                $tempRow['additional_charge_tax_amount'] = strval($this->getAdditionalChargeTaxAmount($row['additional_charges'] ?? null));
                $tempRow['promo_code'] = $row['promo_code'];
                $tempRow['promo_discount'] = $row['promo_discount'];
                $tempRow['final_total'] = (str_replace(',', '', $row['final_total']));
                $tempRow['admin_earnings'] = $row['admin_earnings'];
                $tempRow['partner_earnings'] = $row['partner_earnings'];
                $tempRow['address_id'] = $row['address_id'];
                $tempRow['partner_name'] = $providerName;
                $tempRow['overall_total'] = (str_replace(',', '', $finalTotal));


                // Remove empty values between commas
                $cleaned_address = preg_replace('/,+/', ',', $row['address']);  // Replaces multiple commas with a single comma

                // Remove leading and trailing commas (if any)
                $cleaned_address = trim($cleaned_address, ',');
                $tempRow['address'] = $cleaned_address;
                $tempRow['custom_job_request_id'] = $row['custom_job_request_id'];
                //start
                $tempRow['is_online_payment_allowed'] = $check_payment_gateway['payment_gateway_setting'];
                $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $row['partner_id'], 'status' => 'active']);
                if (!empty($active_partner_subscription)) {
                    if ($active_partner_subscription[0]['is_commision'] == "yes") {
                        $commission_threshold = $active_partner_subscription[0]['commission_threshold'];
                    } else {
                        $commission_threshold = 0;
                    }
                } else {
                    $commission_threshold = 0;
                }
                if ($check_payment_gateway['cod_setting'] == 1 && $check_payment_gateway['payment_gateway_setting'] == 0) {
                    $tempRow['is_pay_later_allowed'] = (string) 1;
                } else if ($check_payment_gateway['cod_setting'] == 0) {
                    $tempRow['is_pay_later_allowed'] = (string) 0;
                } else {
                    $payable_commission_of_provider = $row['payable_commision'];
                    if (($payable_commission_of_provider >= $commission_threshold) && $commission_threshold != 0) {
                        $tempRow['is_pay_later_allowed'] = (string) 0;
                    } else {
                        $tempRow['is_pay_later_allowed'] = (string) 1;
                    }
                }
                //end
                if (!empty($row['additional_charges'])) {
                    $tempRow['additional_charges'] = json_decode($row['additional_charges'], true);
                } else {
                    $tempRow['additional_charges'] = []; // or null, depending on your needs
                }
                $tempRow['payment_status_of_additional_charge'] = $row['payment_status_of_additional_charge'];
                $tempRow['total_additional_charge'] = $row['total_additional_charge'];
                $tempRow['payment_method_of_additional_charge'] = $row['payment_method_of_additional_charge'];
                if ($row['payment_method_of_additional_charge'] == "cod" && $row['status'] == "completed" && ($row['total_additional_charge'] != 0 || $row['total_additional_charge'] != "")) {
                    $tempRow['payment_status_of_additional_charge'] = '1';
                }
                if ($row['payment_method_of_additional_charge'] == "cod" && $row['status'] != "completed" && ($row['total_additional_charge'] != 0 || $row['total_additional_charge'] != "")) {
                    $tempRow['payment_status_of_additional_charge'] = "0";
                }
                $tempRow['payment_status'] = $this->getPaymentStatus($row['payment_method'], $row['status'], $row['payment_status'] ?? null);
                if (!$from_app) {
                    $tempRow['date_of_service'] = format_date($row['date_of_service'], 'd-m-Y ');
                } else {
                    $tempRow['date_of_service'] = $row['date_of_service'];
                }
                $tempRow['starting_time'] = ($row['starting_time']);
                $tempRow['ending_time'] = ($row['ending_time']);
                $tempRow['duration'] = $row['duration'];
                $tempRow['partner_address'] = $row['partner_address'];
                $tempRow['partner_no'] = $isMasked ? $maskPhone($row['partner_no']) : $row['partner_no'];
                $tempRow['partner_country_code'] = $isMasked ? '' : ($row['partner_country_code'] ?? '');
                $tempRow['service_image'] = "frg";
                $tempRow['otp'] = $row['otp'];
                $isRefunded = $row['isRefunded'];
                $orderId = $row['id'];
                $tempRow['isRefunded'] = $isRefunded;
                if ($isRefunded === '1') {
                    $transaction = fetch_details('transactions', ['order_id' => $orderId, 'transaction_type' => 'refund']);
                    $tempRow['refundStatus'] = !empty($transaction) ? $transaction[0]['status'] : 'pending';
                } else {
                    $tempRow['refundStatus'] = 'not_requested_for_refund';
                }
                if (!empty($row['work_started_proof'])) {
                    $row['work_started_proof'] = json_decode($row['work_started_proof'], true);
                    foreach ($row['work_started_proof'] as &$ws) {
                        $ws = $fileService->url($ws, 'provider_work_evidence');
                    }
                }
                if (!empty($row['work_completed_proof'])) {
                    $row['work_completed_proof'] = json_decode($row['work_completed_proof'], true);
                    foreach ($row['work_completed_proof'] as &$wc) {
                        $wc = $fileService->url($wc, 'provider_work_evidence');
                    }
                }
                $tempRow['work_started_proof'] = !empty($row['work_started_proof']) ? ($row['work_started_proof']) : [];
                $tempRow['work_completed_proof'] = !empty($row['work_completed_proof']) ? ($row['work_completed_proof']) : [];

                if ($row['custom_job_request_id'] != null || $row['custom_job_request_id'] != "") {
                    $tempRow['is_reorder_allowed'] = "0";
                } else {

                    if ($row['subscription_status'] == "active") {
                        $tempRow['is_reorder_allowed'] = "1";

                        // Get the first service from order services
                        $service_id = null;
                        if (!empty($order_record['order_services']) && is_array($order_record['order_services']) && count($order_record['order_services']) > 0) {
                            $service_id = $order_record['order_services'][0]['service_id'] ?? null;
                        }

                        if ($service_id) {
                            $details = fetch_details('services', ['id' => $service_id], ['id', 'user_id', 'approved_by_admin', 'at_store', 'at_doorstep']);
                            if (empty($details)) {
                                $tempRow['is_reorder_allowed'] = "0"; // No service found
                            } else {
                                $detail = $details[0];

                                $p_details = fetch_details('partner_details', ['partner_id' => $detail['user_id']], ['id', 'at_store', 'at_doorstep', 'need_approval_for_the_service']);
                                if (empty($p_details)) {
                                    $tempRow['is_reorder_allowed'] = "0"; // No partner found
                                } else {
                                    $p_detail = $p_details[0];

                                    if (($detail['at_store'] != $p_detail['at_store']) && ($detail['at_doorstep'] || $detail['at_doorstep'])) {
                                        $tempRow['is_reorder_allowed'] = "0";
                                    }

                                    $is_already_subscribe = fetch_details('partner_subscriptions', ['partner_id' => $detail['user_id'], 'status' => 'active']);

                                    if ($p_detail['need_approval_for_the_service'] == 1) {
                                        if ($detail['approved_by_admin'] != 1 || empty($is_already_subscribe)) {
                                            $tempRow['is_reorder_allowed'] = "0";
                                        }
                                    }
                                }
                            }
                        } else {
                            $tempRow['is_reorder_allowed'] = "0"; // No service ID found
                        }
                    } else {
                        $tempRow['is_reorder_allowed'] = "0";
                    }
                }

                $tempRow['status'] = $status;
                $tempRow['remarks'] = $row['remarks'];
                $tempRow['created_at'] = $row['created_at'];
                $tempRow['company_name'] = $row['company_name'];
                $tempRow['visiting_charges'] = (str_replace(',', '', $row['visiting_charges']));
                $tempRow['services'] = $order_record['order_services'];

                $cancelLanguageCode = $from_app ? $this->getCurrentLanguageFromRequest() : get_current_language();
                $cancelReasonData = $this->resolveCancelReason($row['cancel_reason_id'] ?? null, $cancelLanguageCode);
                $tempRow['cancel_reason_id'] = $cancelReasonData['id'];
                $tempRow['cancel_reason'] = $cancelReasonData['reason'];
                $tempRow['cancel_additional_info'] = $row['cancel_additional_info'] ?? null;

                // Apply translations to the order data if this is an API call
                if ($from_app) {
                    $languageCode = $this->getCurrentLanguageFromRequest();
                    $tempRow = $this->applyTranslationsToOrder($tempRow, $languageCode);
                }

                $settings = \get_settings('general_settings', true);
                $tempRow['is_otp_enalble'] = (!empty($settings['otp_system'])) ? $settings['otp_system'] : "0";
                $tempRow['post_booking_chat'] = (!empty($row['post_booking_chat'])) ? $row['post_booking_chat'] : "0";
                $outerIsCancelable = 1;
                $highestCancelableTill = 0;
                foreach ($order_record["order_services"] as $service) {
                    if (isset($service['custom_job_request_id']) && ($service['custom_job_request_id'] != NULL || $service['custom_job_request_id'] != "")) {
                        $cancelableTill = (int) $service["requested_end_date"];
                        if ($cancelableTill > $highestCancelableTill) {
                            $highestCancelableTill = $cancelableTill;
                        }
                    } else {
                        $cancelableTill = (int) $service["cancelable_till"];
                        if ($cancelableTill > $highestCancelableTill) {
                            $highestCancelableTill = $cancelableTill;
                        }
                        if ($service["is_cancelable"] == 0) {
                            $outerIsCancelable = 0;
                        }
                    }
                }
                if ($row["status"] == "completed") {
                    $outerIsCancelable = 0;
                }
                if ($row["status"] == "booking_ended") {
                    $outerIsCancelable = 0;
                }
                $currentDateTime = new \DateTime("now");
                $targetDateTime = new \DateTime($row["date_of_service"] . " " . $row["starting_time"]);
                $targetDateTime->sub(new \DateInterval("PT" . $highestCancelableTill . "M"));
                if ($currentDateTime >= $targetDateTime) {
                    $outerIsCancelable = 0;
                }
                $tempRow['is_cancelable'] = $outerIsCancelable;
                $tempRow['new_start_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date(($row['starting_time']), 'h:i A');
                $temprow_for_suborder = [];
                $builder_sub_order = $db->table('orders o');
                $builder_sub_order->where('o.parent_id', $row['id']);
                $sub_order_record = $builder_sub_order->orderBy('o.id', $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();
                $tempRow['new_end_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date(($row['ending_time']), 'h:i A');
                if (empty($sub_order_record)) {
                    $tempRow['multiple_days_booking'] = [];
                }
                foreach ($sub_order_record as $key => $sub_row) {
                    if (!$from_app) {
                        $temprow_for_suborder[$key]['multiple_day_date_of_service'] = date("d-M-Y", strtotime($sub_row['date_of_service']));
                        $temprow_for_suborder[$key]['multiple_day_starting_time'] = date("h:i A", strtotime($sub_row['starting_time']));
                        $temprow_for_suborder[$key]['multiple_ending_time'] = date("h:i A", strtotime($sub_row['ending_time']));
                        ;
                    } else {
                        $temprow_for_suborder[$key]['multiple_day_date_of_service'] = $sub_row['date_of_service'];
                        $temprow_for_suborder[$key]['multiple_day_starting_time'] = $sub_row['starting_time'];
                        $temprow_for_suborder[$key]['multiple_ending_time'] = $sub_row['ending_time'];
                    }
                    $tempRow['multiple_days_booking'] = $temprow_for_suborder;
                }
                if (!empty($sub_order_record)) {
                    $tempRow['new_end_time_with_date'] = date("d-M-Y", strtotime($sub_order_record[0]['date_of_service'])) . ' ' . date("h:i A", strtotime($sub_order_record[0]['ending_time']));
                }
                $tempRow['invoice_no'] = 'INV-' . $row['id'];
                $is_already_exist_query = fetch_details('enquiries', ['customer_id' => $row['user_id'], 'booking_id' => $row['id']]);
                if (empty($is_already_exist_query)) {
                    $e_id = "";
                } else {
                    $e_id = $is_already_exist_query[0]['id'];
                }
                $tempRow['e_id'] = $e_id;
                if (!$from_app) {
                    $tempRow['operations'] = $operations;
                    unset($tempRow['updated_at']);
                }
                // print_r($tempRow); die;
                $rows[] = $tempRow;
            }
        }
        // Rebuild address strings from custom fields.
        Addresses_model::rebuildAddressOnOrderRows($rows);

        $bulkData['rows'] = $rows;
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            return json_encode($bulkData);
        }
    }

    /**
     * Lean data source for the admin `admin/orders/list` bootstrap-table.
     * Selects only the columns the table actually renders (see orders.php th[data-field]) —
     * unlike list(), it does not fetch order_services/ratings/tax/subscription/reorder/multi-day
     * data per row, which was the source of the 26-30s load (N+1 fetch_details() calls per row).
     * Reads filters from $_GET/$_POST the same way list() does, since bootstrap-table drives this
     * endpoint via query params (see orders_query() in orders.php).
     */
    public function listForBookingsTable(): string
    {
        $fileService = service('fileService');
        $db = \Config\Database::connect();

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $order = $_GET['order'] ?? 'DESC';
        $search = $_GET['search'] ?? '';

        $sortFieldMap = [
            'id' => 'o.id',
            'user_id' => 'o.user_id',
            'city_id' => 'o.city_id',
            'total' => 'o.total',
            'promo_code' => 'o.promo_code',
            'promo_discount' => 'o.promo_discount',
            'final_total' => 'o.final_total',
            'admin_earnings' => 'o.admin_earnings',
            'partner_earnings' => 'o.partner_earnings',
            'date_of_service' => 'o.date_of_service',
            'new_start_time_with_date' => 'o.starting_time',
            'new_end_time_with_date' => 'o.ending_time',
            'duration' => 'o.duration',
            'status' => 'o.status',
        ];
        $sort = $sortFieldMap[$_GET['sort'] ?? 'id'] ?? 'o.id';

        $multipleWhere = [];
        if ($search !== '') {
            $multipleWhere = [
                '`o.id`' => $search,
                '`o.user_id`' => $search,
                '`o.partner_id`' => $search,
                '`o.total`' => $search,
                '`o.address`' => $search,
                '`o.date_of_service`' => $search,
                '`o.starting_time`' => $search,
                '`o.ending_time`' => $search,
                '`o.duration`' => $search,
                '`o.status`' => $search,
                '`o.remarks`' => $search,
                '`up.username`' => $search,
                '`u.username`' => $search,
                '`os.service_title`' => $search,
                '`os.status`' => $search,
            ];
        }

        $applyCommonFilters = function ($builder) use ($multipleWhere) {
            if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
                $builder->where('o.status', $_GET['order_status_filter']);
            }
            if (isset($_GET['order_provider_filter']) && $_GET['order_provider_filter'] != '') {
                $builder->where('o.partner_id', $_GET['order_provider_filter']);
            }
            if (isset($_POST['status']) && $_POST['status'] != '') {
                $builder->where('o.status', $_POST['status']);
            }
            if (!empty($multipleWhere)) {
                $builder->groupStart()->orLike($multipleWhere)->groupEnd();
            }
            $builder->where('o.parent_id', null);
        };

        // --- Count ---
        $countBuilder = $db->table('orders o')
            ->select('count(DISTINCT(o.id)) as total')
            ->join('order_services os', 'os.order_id = o.id')
            ->join('users u', 'u.id = o.user_id')
            ->join('users up', 'up.id = o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id');
        $applyCommonFilters($countBuilder);
        $total = (int) ($countBuilder->get()->getRowArray()['total'] ?? 0);

        $bulkData = ['total' => $total, 'rows' => []];
        if ($total === 0) {
            return json_encode($bulkData);
        }

        // --- Page of rows: only table columns, provider name/company resolved via
        // a single pivoted LEFT JOIN (multilang-resolve pattern) instead of a per-row query.
        $currentLang = $db->escape(get_current_language());
        $defaultLang = $db->escape(get_default_language());
        $tpdPivot = "(
            SELECT partner_id,
                   MAX(CASE WHEN language_code = $currentLang THEN NULLIF(username, '') END) AS cur_username,
                   MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(username, '') END) AS def_username,
                   MAX(CASE WHEN language_code = $currentLang THEN NULLIF(company_name, '') END) AS cur_company_name,
                   MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(company_name, '') END) AS def_company_name
            FROM translated_partner_details
            WHERE language_code IN ($currentLang, $defaultLang)
            GROUP BY partner_id
        ) t";

        $builder = $db->table('orders o')
            ->select("o.id, o.user_id, o.partner_id, o.city, o.total, o.promo_code, o.promo_discount, o.final_total,
                o.admin_earnings, o.partner_earnings, o.address_id, o.address, o.date_of_service, o.starting_time,
                o.ending_time, o.duration, o.status, o.remarks,
                u.username as user_name,
                up.image as provider_profile_image, up.email as partner_email, up.phone as partner_phone,
                COALESCE(t.cur_username, t.def_username, up.username) as partner_name,
                COALESCE(t.cur_company_name, t.def_company_name, pd.company_name) as company_name", false)
            ->join('order_services os', 'os.order_id = o.id')
            ->join('users u', 'u.id = o.user_id')
            ->join('users up', 'up.id = o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id')
            ->join($tpdPivot, 't.partner_id = pd.partner_id', 'left', false);
        $applyCommonFilters($builder);
        $rows = $builder->orderBy($sort, $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();

        // Batch-fetch the last day of multi-day (parent_id) bookings for the whole page in one query,
        // instead of one sub-order query per row.
        $ids = array_column($rows, 'id');
        $lastDayByParent = [];
        if (!empty($ids)) {
            $subOrders = $db->table('orders')
                ->select('parent_id, date_of_service, ending_time')
                ->whereIn('parent_id', $ids)
                ->orderBy('id', $order)
                ->get()
                ->getResultArray();
            foreach ($subOrders as $subOrder) {
                if (!isset($lastDayByParent[$subOrder['parent_id']])) {
                    $lastDayByParent[$subOrder['parent_id']] = $subOrder;
                }
            }
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $cleanedAddress = trim(preg_replace('/,+/', ',', (string) $row['address']), ',');

            $tempRow = [];
            $tempRow['id'] = $row['id'];
            $tempRow['partner'] = $this->buildProviderProfileHtml($row, $fileService);
            $tempRow['user_id'] = $row['user_id'];
            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['customer'] = $row['user_name'];
            $tempRow['city_id'] = $row['city'];
            $tempRow['total'] = str_replace(',', '', $row['total']);
            $tempRow['promo_code'] = $row['promo_code'];
            $tempRow['promo_discount'] = $row['promo_discount'];
            $tempRow['final_total'] = str_replace(',', '', $row['final_total']);
            $tempRow['admin_earnings'] = $row['admin_earnings'];
            $tempRow['partner_earnings'] = $row['partner_earnings'];
            $tempRow['address_id'] = $row['address_id'];
            $tempRow['address'] = $cleanedAddress;
            $tempRow['date_of_service'] = format_date($row['date_of_service'], 'd-m-Y ');
            $tempRow['new_start_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date($row['starting_time'], 'h:i A');
            if (isset($lastDayByParent[$row['id']])) {
                $lastDay = $lastDayByParent[$row['id']];
                $tempRow['new_end_time_with_date'] = format_date($lastDay['date_of_service'], 'd-m-Y') . ' ' . format_date($lastDay['ending_time'], 'h:i A');
            } else {
                $tempRow['new_end_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date($row['ending_time'], 'h:i A');
            }
            $tempRow['duration'] = $row['duration'];
            $tempRow['status'] = $this->renderStatusBadge($row['status']);
            $tempRow['remarks'] = $row['remarks'];
            $tempRow['operations'] = $this->buildOperationsHtml($row);

            $tableRows[] = $tempRow;
        }

        // Rebuild address strings from custom fields.
        Addresses_model::rebuildAddressOnOrderRows($tableRows);

        $bulkData['rows'] = $tableRows;
        return json_encode($bulkData);
    }

    /**
     * Admin booking status pill — same markup as the admin branch of list().
     * Kept private/local to listForBookingsTable(); list() is left untouched
     * to avoid touching a method shared with the customer/partner APIs.
     */
    private function renderStatusBadge(string $status): string
    {
        $map = [
            'awaiting' => ['bg-emerald-warning text-emerald-warning', 'awaiting', 'Awaiting'],
            'confirmed' => ['bg-emerald-blue text-emerald-blue', 'confirmed', 'Confirmed'],
            'rescheduled' => ['bg-emerald-grey text-emerald-grey', 'rescheduled', 'Rescheduled'],
            'cancelled' => ['bg-emerald-danger text-emerald-danger', 'cancelled', 'Cancelled'],
            'completed' => ['bg-emerald-success text-emerald-success', 'completed', 'Completed'],
            'pending' => ['bg-emerald-grey text-emerald-grey', 'pending', 'Pending'],
            'started' => ['bg-emerald-warning text-emerald-warning', 'started', 'Started'],
            'on_the_way' => ['bg-emerald-blue text-emerald-blue', 'on_the_way', 'On The Way'],
            'arrived' => ['bg-emerald-purple text-emerald-purple', 'arrived', 'Arrived'],
            'booking_ended' => ['bg-emerald-warning text-emerald-warning', 'booking_ended', 'Booking Ended'],
        ];
        if (!isset($map[$status])) {
            return labels('undefined_status', 'Status Not Defined');
        }
        [$classes, $labelKey, $labelDefault] = $map[$status];
        return "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 $classes dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels($labelKey, $labelDefault) . "</div>";
    }

    /**
     * Provider profile card markup used by the 'partner' table column —
     * shared by listForBookingsTable() (admin) and listForPartnerBookingsTable() (partner).
     */
    private function buildProviderProfileHtml(array $row, $fileService): string
    {
        $imageSrc = $fileService->url($row['provider_profile_image'], 'profile', 'public/backend/assets/default.png');
        $profile = '<div class="o-media o-media--middle">
                    <a href="' . $imageSrc . '" data-lightbox="image-1">
                        <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $row['partner_name'] . '">
                    </a>';
        $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
            <div class="provider_name_table" >' . $row['partner_name'] . '</span></div>
            <div class="provider_email_table">' . $row['company_name'] . '</div>
            <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
            </div>
            </div></a>';
        return $profile;
    }

    /**
     * Lean data source for the partner `partner/orders/list` bootstrap-table.
     * Same rationale as listForBookingsTable() (admin) — selects only columns
     * orders.php (partner) renders, no per-row fetch_details()/sub-query N+1.
     * Scoped to the logged-in partner via $partnerId.
     */
    public function listForPartnerBookingsTable(int $partnerId): string
    {
        $fileService = service('fileService');
        $db = \Config\Database::connect();

        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? (int) $_GET['limit'] : 20;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? (int) $_GET['offset'] : 0;
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

        $sortFieldMap = [
            'id' => 'o.id',
            'date_of_service' => 'o.date_of_service',
            'user_id' => 'o.user_id',
            'final_total' => 'o.final_total',
            'total' => 'o.total',
            'promo_code' => 'o.promo_code',
            'promo_discount' => 'o.promo_discount',
            'admin_earnings' => 'o.admin_earnings',
            'partner_earnings' => 'o.partner_earnings',
            'new_start_time_with_date' => 'o.starting_time',
            'new_end_time_with_date' => 'o.ending_time',
            'duration' => 'o.duration',
            'status' => 'o.status',
        ];
        $sort = $sortFieldMap[$_GET['sort'] ?? 'id'] ?? 'o.id';

        $multipleWhere = [];
        if ($search !== '') {
            $multipleWhere = [
                '`o.id`' => $search,
                '`o.total`' => $search,
                '`o.address`' => $search,
                '`o.date_of_service`' => $search,
                '`o.starting_time`' => $search,
                '`o.ending_time`' => $search,
                '`o.duration`' => $search,
                '`o.status`' => $search,
                '`o.remarks`' => $search,
                '`u.username`' => $search,
                '`os.service_title`' => $search,
                '`os.status`' => $search,
            ];
        }

        $applyCommonFilters = function ($builder) use ($partnerId, $multipleWhere) {
            $builder->where('o.partner_id', $partnerId);
            if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
                $builder->where('o.status', $_GET['order_status_filter']);
            }
            if (!empty($multipleWhere)) {
                $builder->groupStart()->orLike($multipleWhere)->groupEnd();
            }
            $builder->where('o.parent_id', null);
        };

        // --- Count ---
        $countBuilder = $db->table('orders o')
            ->select('count(DISTINCT(o.id)) as total')
            ->join('order_services os', 'os.order_id = o.id')
            ->join('users u', 'u.id = o.user_id');
        $applyCommonFilters($countBuilder);
        $total = (int) ($countBuilder->get()->getRowArray()['total'] ?? 0);

        $bulkData = ['total' => $total, 'rows' => []];
        if ($total === 0) {
            return json_encode($bulkData);
        }

        $currentLang = $db->escape(get_current_language());
        $defaultLang = $db->escape(get_default_language());
        $tpdPivot = "(
            SELECT partner_id,
                   MAX(CASE WHEN language_code = $currentLang THEN NULLIF(username, '') END) AS cur_username,
                   MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(username, '') END) AS def_username,
                   MAX(CASE WHEN language_code = $currentLang THEN NULLIF(company_name, '') END) AS cur_company_name,
                   MAX(CASE WHEN language_code = $defaultLang THEN NULLIF(company_name, '') END) AS def_company_name
            FROM translated_partner_details
            WHERE language_code IN ($currentLang, $defaultLang)
            GROUP BY partner_id
        ) t";

        $builder = $db->table('orders o')
            ->select("o.id, o.user_id, o.partner_id, o.total, o.promo_code, o.promo_discount, o.final_total,
                o.admin_earnings, o.partner_earnings, o.address_id, o.address, o.visiting_charges, o.date_of_service,
                o.starting_time, o.ending_time, o.duration, o.status, o.remarks,
                u.username as user_name,
                up.image as provider_profile_image, up.email as partner_email, up.phone as partner_phone,
                COALESCE(t.cur_username, t.def_username, up.username) as partner_name,
                COALESCE(t.cur_company_name, t.def_company_name, pd.company_name) as company_name", false)
            ->join('order_services os', 'os.order_id = o.id')
            ->join('users u', 'u.id = o.user_id')
            ->join('users up', 'up.id = o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id')
            ->join($tpdPivot, 't.partner_id = pd.partner_id', 'left', false);
        $applyCommonFilters($builder);
        $rows = $builder->orderBy($sort, $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();

        $ids = array_column($rows, 'id');
        $lastDayByParent = [];
        if (!empty($ids)) {
            $subOrders = $db->table('orders')
                ->select('parent_id, date_of_service, ending_time')
                ->whereIn('parent_id', $ids)
                ->orderBy('id', $order)
                ->get()
                ->getResultArray();
            foreach ($subOrders as $subOrder) {
                if (!isset($lastDayByParent[$subOrder['parent_id']])) {
                    $lastDayByParent[$subOrder['parent_id']] = $subOrder;
                }
            }
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $cleanedAddress = trim(preg_replace('/,+/', ',', (string) $row['address']), ',');

            $tempRow = [];
            $tempRow['id'] = $row['id'];
            $tempRow['invoice_no'] = 'INV-' . $row['id'];
            $tempRow['partner'] = $this->buildProviderProfileHtml($row, $fileService);
            $tempRow['user_id'] = $row['user_id'];
            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['customer'] = $row['user_name'];
            $tempRow['total'] = str_replace(',', '', $row['total']);
            $tempRow['promo_code'] = $row['promo_code'];
            $tempRow['promo_discount'] = $row['promo_discount'];
            $tempRow['final_total'] = str_replace(',', '', $row['final_total']);
            $tempRow['admin_earnings'] = $row['admin_earnings'];
            $tempRow['partner_earnings'] = $row['partner_earnings'];
            $tempRow['address_id'] = $row['address_id'];
            $tempRow['address'] = $cleanedAddress;
            $tempRow['visiting_charges'] = str_replace(',', '', $row['visiting_charges']);
            $tempRow['date_of_service'] = format_date($row['date_of_service'], 'd-m-Y ');
            $tempRow['new_start_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date($row['starting_time'], 'h:i A');
            if (isset($lastDayByParent[$row['id']])) {
                $lastDay = $lastDayByParent[$row['id']];
                $tempRow['new_end_time_with_date'] = format_date($lastDay['date_of_service'], 'd-m-Y') . ' ' . format_date($lastDay['ending_time'], 'h:i A');
            } else {
                $tempRow['new_end_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date($row['ending_time'], 'h:i A');
            }
            $tempRow['duration'] = $row['duration'];
            $tempRow['status'] = $this->renderStatusBadge($row['status']);
            $tempRow['remarks'] = $row['remarks'];
            $tempRow['operations'] = $this->buildOperationsHtml($row);

            $tableRows[] = $tempRow;
        }

        // Rebuild address strings from custom fields.
        Addresses_model::rebuildAddressOnOrderRows($tableRows);

        $bulkData['rows'] = $tableRows;
        return json_encode($bulkData);
    }

    public function custom_booking_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $where_in_key = '', $where_in_value = [], $addition_data = '', $download_invoice = false, $newUI = false, $is_provider = false)
    {

        $fileService = service('fileService');

        $db = \Config\Database::connect();
        $sessionEmail = $_SESSION['email'] ?? '';
        $isMasked = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $sessionEmail != 'superadmin@gmail.com';
        $maskPhone = function ($value) {
            return strlen($value) > 6 ? 'XXXXX' . substr($value, 6) : 'XXXXX';
        };
        $maskEmail = function ($value) {
            return strlen($value) > 6 ? 'wrteam.' . substr($value, 6) : 'wrteam.***';
        };
        $builder = $db->table('orders o');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'o.id') {
                $sort = "o.id";
            } else if ($_GET['sort'] == 'customer') {
                $sort = "u.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`o.id`' => $search,
                '`o.user_id`' => $search,
                '`o.partner_id`' => $search,
                '`o.total`' => $search,
                '`o.address`' => $search,
                '`o.date_of_service`' => $search,
                '`o.starting_time`' => $search,
                '`o.ending_time`' => $search,
                '`o.duration`' => $search,
                '`o.status`' => $search,
                '`o.remarks`' => $search,
                '`up.username`' => $search,
                '`u.username`' => $search,
                '`os.service_title`' => $search,
                '`os.status`' => $search,
            ];
        }
        $order_count = $builder->select('count(DISTINCT(o.id)) as total')
            ->join('order_services os', 'os.order_id=o.id')
            ->join('users u', 'u.id=o.user_id')
            ->join('users up', 'up.id=o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id');
        if (isset($_GET['filter_date']) && $_GET['filter_date'] != '') {
            $builder->where('o.created_at', $_GET['filter_date']);
        }
        if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
            $builder->where('o.status', $_GET['order_status_filter']);
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'o.id') {
                $sort = "o.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (!empty($where_in_key) && !empty($where_in_value)) {
            $builder->whereIn($where_in_key, $where_in_value);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $order_count = $builder->get()->getResultArray();
        $total = $order_count[0]['total'];
        $builder
            ->select('o.*, t.status as payment_status, pd.advance_booking_days,
        u.id as customer_id, u.username as user_name, u.image as user_image, u.phone as customer_no,o.payment_status_of_additional_charge,o.payment_method_of_additional_charge,
        u.latitude as latitude, u.longitude as longitude, partner_subscriptions.name as subscription_name,partner_subscriptions.id as partner_subscription_id,partner_subscriptions.status as subscription_status,
        up.image as provider_profile_image, u.email as customer_email, up.username as partner_name,pd.chat as post_booking_chat, pd.pre_chat as pre_booking_chat,
        up.phone as partner_no, u.balance as user_wallet, up.latitude as partner_latitude,u.payable_commision,
        up.longitude as partner_longitude, pd.company_name, o.visiting_charges, pd.address as partner_address')
            ->join('order_services os', 'os.order_id = o.id')
            ->join('users u', 'u.id = o.user_id')
            ->join('users up', 'up.id = o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id')
            ->join('(SELECT partner_id, MAX(created_at) AS latest_subscription_date 
            FROM partner_subscriptions 
            GROUP BY partner_id) latest_subscriptions', 'latest_subscriptions.partner_id = pd.partner_id')
            ->join('partner_subscriptions', 'partner_subscriptions.partner_id = latest_subscriptions.partner_id AND partner_subscriptions.created_at = latest_subscriptions.latest_subscription_date', 'left')
            ->join('transactions t', 't.order_id = o.id', 'left');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (!empty($where_in_key) && !empty($where_in_value)) {
            $builder->whereIn($where_in_key, $where_in_value);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if (isset($_GET['order_status_filter']) && $_GET['order_status_filter'] != '') {
            $builder->where('o.status', $_GET['order_status_filter']);
        }
        if (isset($_GET['order_provider_filter']) && $_GET['order_provider_filter'] != '') {
            $builder->where('o.partner_id', $_GET['order_provider_filter']);
        }
        if (isset($_POST['status']) && $_POST['status'] != '') {
            $builder->where('o.status', $_POST['status']);
        }
        $builder->where('o.parent_id', null);
        $order_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $check_payment_gateway = get_settings('payment_gateways_settings', true);
        if (empty($order_record)) {
            $bulkData = array();
        } else {
            foreach ($order_record as $row) {
                $builder = $db->table('order_services os');
                $services = $builder->select('
                os.id as id,
                os.order_id,
                cj.id as custom_job_request_id,
                os.service_title,
                os.service_title_translations,
                os.tax_percentage,
                os.discount_price,
                os.tax_amount,
                os.price,
                os.quantity,
                os.sub_total,
                os.status,       
                os.custom_job_request_id,     
                categories.name as category_name,           
                ,pb.duration,cj.category_id,cj.service_title,pb.tax_id,sr.rating,sr.comment,sr.images,pb.note,cj.service_short_description')
                    ->where('os.order_id', $row['id'])
                    ->join('custom_job_requests as cj', 'cj.id=os.custom_job_request_id', 'left')
                    ->join('categories', 'categories.id=cj.category_id', 'left')
                    ->join('partner_bids as pb', 'pb.custom_job_request_id=os.custom_job_request_id', 'left')
                    ->join('services_ratings as sr', 'sr.custom_job_request_id=os.custom_job_request_id AND sr.user_id=' . $row["user_id"] . '', 'left')->groupBy('os.order_id')->get()->getResultArray();
                $order_record['order_services'] = $services;
                // $db = \Config\Database::connect();  
                // // your queries here
                // $query = $db->getLastQuery();
                // $sql = $query->getQuery();
                // echo $sql;
                // die;

                // print_R($services);
                // die;

                foreach ($order_record['order_services'] as $key => $os) {
                    $taxPercentageData = fetch_details('taxes', ['id' => $os['tax_id']], ['percentage']);
                    if (!empty($taxPercentageData)) {
                        $taxPercentage = $taxPercentageData[0]['percentage'];
                    } else {
                        $taxPercentage = 0;
                    }
                    $order_record['order_services'][$key]['service_short_description'] = $os['service_short_description'];
                    $order_record['order_services'][$key]['note'] = $os['note'];
                    // Tax value must always be present (included = extract from price, excluded = on top)
                    $isTaxExcludedApi = !empty($os['tax_type']) && $os['tax_type'] === 'excluded';
                    if ($os['discount_price'] == "0") {
                        $baseP = (float) $os['price'];
                        if ($isTaxExcludedApi) {
                            $taxVal = $baseP * (float) $taxPercentage / 100;
                            $order_record['order_services'][$key]['price_with_tax'] = strval(str_replace(',', '', number_format($baseP + $taxVal, 2)));
                            $order_record['order_services'][$key]['tax_value'] = strval(str_replace(',', '', number_format($taxVal, 2)));
                            $order_record['order_services'][$key]['original_price_with_tax'] = strval(str_replace(',', '', number_format($baseP + $taxVal, 2)));
                        } else {
                            $taxVal = calculate_tax_amount($baseP, (float) $taxPercentage, 'included');
                            $order_record['order_services'][$key]['price_with_tax'] = strval(str_replace(',', '', number_format($baseP, 2)));
                            $order_record['order_services'][$key]['tax_value'] = strval(str_replace(',', '', number_format($taxVal, 2)));
                            $order_record['order_services'][$key]['original_price_with_tax'] = strval(str_replace(',', '', number_format($baseP, 2)));
                        }
                    } else {
                        $baseP = (float) $os['discount_price'];
                        $origP = (float) $os['price'];
                        if ($isTaxExcludedApi) {
                            $taxVal = $baseP * (float) $taxPercentage / 100;
                            $order_record['order_services'][$key]['price_with_tax'] = strval(str_replace(',', '', number_format($baseP + $taxVal, 2)));
                            $order_record['order_services'][$key]['tax_value'] = number_format($taxVal, 2);
                            $order_record['order_services'][$key]['original_price_with_tax'] = strval(str_replace(',', '', number_format($origP + ($origP * (float) $taxPercentage / 100), 2)));
                        } else {
                            $taxVal = calculate_tax_amount($baseP, (float) $taxPercentage, 'included');
                            $order_record['order_services'][$key]['price_with_tax'] = strval(str_replace(',', '', number_format($baseP, 2)));
                            $order_record['order_services'][$key]['tax_value'] = number_format($taxVal, 2);
                            $order_record['order_services'][$key]['original_price_with_tax'] = strval(str_replace(',', '', number_format($origP, 2)));
                        }
                    }
                    // $order_record['order_services'][$key]['image'] =  $os['images'];
                    if (empty($os['images'])) {
                        $os['images'] = [];
                    } else {
                        $image_paths = json_decode($os['images'], true);
                        if ($image_paths !== null) {
                            $updated_images = [];
                            foreach ($image_paths as $path) {
                                $updated_images[] = $fileService->url($path, 'ratings');
                            }
                            $os['images'] = $updated_images;
                        } else {
                            $os['images'] = [];
                        }
                    }
                    $order_record['order_services'][$key]['images'] = $os['images'];

                    // Fix title field: if title is empty, use service_title as fallback
                    if (empty($order_record['order_services'][$key]['title'])) {
                        $order_record['order_services'][$key]['title'] = $order_record['order_services'][$key]['service_title'] ?? '';
                    }

                    // Add category_id, category_name and translated_category_name to service object (same as normal bookings)
                    // Ensure category_id is set (already fetched from query as cj.category_id)
                    $order_record['order_services'][$key]['category_id'] = $os['category_id'] ?? '';
                    // Ensure category_name is set (already fetched from query)
                    $order_record['order_services'][$key]['category_name'] = $os['category_name'] ?? '';

                    // Get translated category name using the same helper function as normal bookings
                    if (!empty($os['category_id'])) {
                        $categoryFallbackData = ['name' => $os['category_name'] ?? ''];
                        $translatedCategoryData = get_translated_category_data_for_api($os['category_id'], $categoryFallbackData);
                        $order_record['order_services'][$key]['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $os['category_name'] ?? '';
                    } else {
                        $order_record['order_services'][$key]['translated_category_name'] = $os['category_name'] ?? '';
                    }
                }
                if ($from_app == false) {
                    // Use PermissionService (cached) instead of raw DB + get_permission().
                    $permissions = $this->permissionService->permissionsFor($this->admin_id);
                }
                $operations = '';
                $status = $row['status'];
                $tax_amount = 0;
                foreach ($order_record['order_services'] as $order_data) {
                    $tax_amount = number_format($tax_amount, 2) + ((($order_data['tax_amount'])) * $order_data['quantity']);
                }
                $tax_amount += $this->getAdditionalChargeTaxAmount($row['additional_charges'] ?? null);
                $s = [];
                $row['provider_profile_image'] = !empty($row['provider_profile_image'])
                    ? $fileService->url($row['provider_profile_image'], 'profile')
                    : '';

                $row['user_image'] = !empty($row['user_image'])
                    ? $fileService->url(basename($row['user_image']), 'profile')
                    : '';

                $tempRow['id'] = $row['id'];
                $tempRow['slug'] = 'inv-' . $row['id'];
                $tempRow['customer'] = $row['user_name'];
                $tempRow['customer_id'] = $row['customer_id'];
                $tempRow['profile_image'] = ($is_provider) ? ($row['user_image'] ?? '') : ($row['provider_profile_image'] ?? '');
                $tempRow['latitude'] = $row['order_latitude'];
                $tempRow['longitude'] = $row['order_longitude'];
                $tempRow['partner_latitude'] = $row['partner_latitude'];
                $tempRow['partner_longitude'] = $row['partner_longitude'];
                $tempRow['advance_booking_days'] = $row['advance_booking_days'];
                $tempRow['customer_no'] = $isMasked ? $maskPhone($row['customer_no']) : $row['customer_no'];
                $tempRow['customer_email'] = $isMasked ? $maskEmail($row['customer_email']) : $row['customer_email'];
                $tempRow['user_wallet'] = $row['user_wallet'];
                $tempRow['payment_method'] = $row['payment_method'];
                $tempRow['payment_status'] = $row['payment_status'];

                // Get provider name with language fallback: current language → default language → base table
                // Priority: current language translation → default language translation → base table company_name
                $providerName = $row['partner_name']; // Default fallback to partner_name from query
                if (!empty($row['partner_id'])) {
                    $currentLang = get_current_language();
                    $defaultLang = get_default_language();

                    // Get translated partner details
                    $translatedPartnerModel = new \App\Models\TranslatedPartnerDetails_model();
                    $allTranslations = $translatedPartnerModel->getAllTranslationsForPartner($row['partner_id']);

                    if (!empty($allTranslations)) {
                        $currentTranslation = null;
                        $defaultTranslation = null;

                        // Organize translations by language code
                        $translationsByLang = [];
                        foreach ($allTranslations as $translation) {
                            $translationsByLang[$translation['language_code']] = $translation;
                        }

                        // Try current language first
                        if (!empty($translationsByLang[$currentLang]['company_name'])) {
                            $providerName = $translationsByLang[$currentLang]['company_name'];
                        } elseif (!empty($translationsByLang[$defaultLang]['company_name'])) {
                            // Fallback to default language
                            $providerName = $translationsByLang[$defaultLang]['company_name'];
                        } elseif (!empty($row['company_name'])) {
                            // Final fallback to base table
                            $providerName = $row['company_name'];
                        }
                    } elseif (!empty($row['company_name'])) {
                        // If no translations exist, use base table company_name
                        $providerName = $row['company_name'];
                    }
                }
                $tempRow['partner'] = $providerName;
                $tempRow['user_id'] = $row['user_id'];
                $tempRow['partner_id'] = $row['partner_id'];
                $tempRow['city_id'] = $row['city'];
                $tempRow['total'] = (str_replace(',', '', $row['total']));
                $tempRow['tax_amount'] = strval($tax_amount);
                $tempRow['additional_charge_tax_amount'] = strval($this->getAdditionalChargeTaxAmount($row['additional_charges'] ?? null));
                $tempRow['promo_code'] = $row['promo_code'];
                $tempRow['promo_discount'] = $row['promo_discount'];
                $tempRow['final_total'] = (str_replace(',', '', $row['final_total']));
                $tempRow['admin_earnings'] = $row['admin_earnings'];
                $tempRow['partner_earnings'] = $row['partner_earnings'];
                $tempRow['address_id'] = $row['address_id'];
                // Remove empty values between commas
                $cleaned_address = preg_replace('/,+/', ',', $row['address']);  // Replaces multiple commas with a single comma

                // Remove leading and trailing commas (if any)
                $cleaned_address = trim($cleaned_address, ',');
                $tempRow['address'] = $cleaned_address;
                $tempRow['custom_job_request_id'] = $row['custom_job_request_id'];
                //start
                $tempRow['is_online_payment_allowed'] = $check_payment_gateway['payment_gateway_setting'];
                $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $row['partner_id'], 'status' => 'active']);
                if (!empty($active_partner_subscription)) {
                    if ($active_partner_subscription[0]['is_commision'] == "yes") {
                        $commission_threshold = $active_partner_subscription[0]['commission_threshold'];
                    } else {
                        $commission_threshold = 0;
                    }
                } else {
                    $commission_threshold = 0;
                }
                if ($check_payment_gateway['cod_setting'] == 1 && $check_payment_gateway['payment_gateway_setting'] == 0) {
                    $tempRow['is_pay_later_allowed'] = (string) 1;
                } else if ($check_payment_gateway['cod_setting'] == 0) {
                    $tempRow['is_pay_later_allowed'] = (string) 0;
                } else {
                    $payable_commission_of_provider = $row['payable_commision'];
                    if (($payable_commission_of_provider >= $commission_threshold) && $commission_threshold != 0) {
                        $tempRow['is_pay_later_allowed'] = (string) 0;
                    } else {
                        $tempRow['is_pay_later_allowed'] = (string) 1;
                    }
                }
                if (!empty($row['additional_charges'])) {
                    $tempRow['additional_charges'] = json_decode($row['additional_charges'], true);
                } else {
                    $tempRow['additional_charges'] = [];
                }
                $tempRow['payment_status_of_additional_charge'] = $row['payment_status_of_additional_charge'];
                if ($row['payment_method_of_additional_charge'] == "cod" && $row['status'] == "completed" && ($row['total_additional_charge'] != 0 || $row['total_additional_charge'] != "")) {
                    $tempRow['payment_status_of_additional_charge'] = '1';
                }
                if ($row['payment_method_of_additional_charge'] == "cod" && $row['status'] != "completed" && ($row['total_additional_charge'] != 0 || $row['total_additional_charge'] != "")) {
                    $tempRow['payment_status_of_additional_charge'] = "0";
                }
                $tempRow['total_additional_charge'] = $row['total_additional_charge'];
                $tempRow['payment_method_of_additional_charge'] = $row['payment_method_of_additional_charge'];
                $tempRow['payment_status'] = $this->getPaymentStatus($row['payment_method'], $row['status'], $row['payment_status'] ?? null);
                if (!$from_app) {
                    $tempRow['date_of_service'] = format_date($row['date_of_service'], 'd-m-Y ');
                } else {
                    $tempRow['date_of_service'] = $row['date_of_service'];
                }
                $tempRow['starting_time'] = ($row['starting_time']);
                $tempRow['ending_time'] = ($row['ending_time']);
                $tempRow['duration'] = $row['duration'];
                $tempRow['partner_address'] = $row['partner_address'];
                $tempRow['partner_no'] = $isMasked ? $maskPhone($row['partner_no']) : $row['partner_no'];
                $tempRow['partner_country_code'] = $isMasked ? '' : ($row['partner_country_code'] ?? '');
                $tempRow['service_image'] = "frg";
                $tempRow['otp'] = $row['otp'];
                $isRefunded = $row['isRefunded'];
                $orderId = $row['id'];
                $tempRow['isRefunded'] = $isRefunded;
                if ($isRefunded === '1') {
                    $transaction = fetch_details('transactions', ['order_id' => $orderId, 'transaction_type' => 'refund']);
                    $tempRow['refundStatus'] = !empty($transaction) ? $transaction[0]['status'] : 'pending';
                } else {
                    $tempRow['refundStatus'] = 'not_requested_for_refund';
                }
                // if (!empty($row['work_started_proof'])) {
                //     $row['work_started_proof'] = array_map(function ($data) {
                //         return base_url($data);
                //     }, json_decode(($row['work_started_proof']), true));
                // }
                // if (!empty($row['work_completed_proof'])) {
                //     $row['work_completed_proof'] = array_map(function ($data) {
                //         return base_url($data);
                //     }, json_decode(($row['work_completed_proof']), true));
                // }
                if (!empty($row['work_started_proof'])) {
                    $row['work_started_proof'] = json_decode($row['work_started_proof'], true);
                    foreach ($row['work_started_proof'] as &$ws) {
                        $ws = $fileService->url($ws, 'provider_work_evidence');
                    }
                }
                if (!empty($row['work_completed_proof'])) {
                    $row['work_completed_proof'] = json_decode($row['work_completed_proof'], true);
                    foreach ($row['work_completed_proof'] as &$wc) {
                        $wc = $fileService->url($wc, 'provider_work_evidence');
                    }
                }
                $tempRow['work_started_proof'] = !empty($row['work_started_proof']) ? ($row['work_started_proof']) : [];
                $tempRow['work_completed_proof'] = !empty($row['work_completed_proof']) ? ($row['work_completed_proof']) : [];
                $tempRow['is_reorder_allowed'] = "0";
                $tempRow['status'] = $status;
                $tempRow['remarks'] = $row['remarks'];
                $tempRow['created_at'] = $row['created_at'];
                $tempRow['company_name'] = $row['company_name'];
                $tempRow['visiting_charges'] = (str_replace(',', '', $row['visiting_charges']));
                $tempRow['services'] = $order_record['order_services'];

                $cancelLanguageCode = $from_app ? $this->getCurrentLanguageFromRequest() : get_current_language();
                $cancelReasonData = $this->resolveCancelReason($row['cancel_reason_id'] ?? null, $cancelLanguageCode);
                $tempRow['cancel_reason_id'] = $cancelReasonData['id'];
                $tempRow['cancel_reason'] = $cancelReasonData['reason'];
                $tempRow['cancel_additional_info'] = $row['cancel_additional_info'] ?? null;

                // Apply translations to the order data if this is an API call
                if ($from_app) {
                    $languageCode = $this->getCurrentLanguageFromRequest();
                    $tempRow = $this->applyTranslationsToOrder($tempRow, $languageCode);

                    // Add category_name and translated_category_name for custom job services
                    // (applyTranslationsToOrder skips custom job services, so we handle them separately)
                    if (!empty($tempRow['services']) && is_array($tempRow['services'])) {
                        foreach ($tempRow['services'] as &$service) {
                            // Only process custom job services (those without service_id)
                            if (empty($service['service_id'])) {
                                // Get category_id from custom_job_request_id if not already set
                                if (empty($service['category_id']) && !empty($service['custom_job_request_id'])) {
                                    $customJobData = fetch_details('custom_job_requests', ['id' => $service['custom_job_request_id']], ['category_id']);
                                    if (!empty($customJobData)) {
                                        $service['category_id'] = $customJobData[0]['category_id'] ?? '';
                                    }
                                }

                                // If we have category_id, set category fields
                                if (!empty($service['category_id'])) {
                                    // Ensure category_name is set
                                    if (empty($service['category_name'])) {
                                        // Try to get it from categories table if not already set
                                        $categoryData = fetch_details('categories', ['id' => $service['category_id']], ['name']);
                                        $service['category_name'] = !empty($categoryData) ? ($categoryData[0]['name'] ?? '') : '';
                                    }

                                    // Get translated category name using the same helper function as normal bookings
                                    $categoryFallbackData = ['name' => $service['category_name'] ?? ''];
                                    $translatedCategoryData = get_translated_category_data_for_api($service['category_id'], $categoryFallbackData);
                                    $service['translated_category_name'] = $translatedCategoryData['translated_name'] ?? $service['category_name'] ?? '';
                                }
                            }
                        }
                        unset($service); // break reference
                    }
                }

                $settings = \get_settings('general_settings', true);
                $tempRow['is_otp_enalble'] = (!empty($settings['otp_system'])) ? $settings['otp_system'] : "0";
                $tempRow['post_booking_chat'] = (!empty($row['post_booking_chat'])) ? $row['post_booking_chat'] : "0";
                if ($row["status"] == "booking_ended" || $row['status'] == "completed") {
                    $tempRow['is_cancelable'] = 0;
                } else {
                    $tempRow['is_cancelable'] = 1;
                }
                $tempRow['new_start_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date(($row['starting_time']), 'h:i A');
                $temprow_for_suborder = [];
                $builder_sub_order = $db->table('orders o');
                $builder_sub_order->where('o.parent_id', $row['id']);
                $sub_order_record = $builder_sub_order->orderBy('o.id', $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();
                $tempRow['new_end_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date(($row['ending_time']), 'h:i A');
                if (empty($sub_order_record)) {
                    $tempRow['multiple_days_booking'] = [];
                }
                foreach ($sub_order_record as $key => $sub_row) {
                    if (!$from_app) {
                        $temprow_for_suborder[$key]['multiple_day_date_of_service'] = date("d-M-Y", strtotime($sub_row['date_of_service']));
                        $temprow_for_suborder[$key]['multiple_day_starting_time'] = date("h:i A", strtotime($sub_row['starting_time']));
                        $temprow_for_suborder[$key]['multiple_ending_time'] = date("h:i A", strtotime($sub_row['ending_time']));
                        ;
                    } else {
                        $temprow_for_suborder[$key]['multiple_day_date_of_service'] = $sub_row['date_of_service'];
                        $temprow_for_suborder[$key]['multiple_day_starting_time'] = $sub_row['starting_time'];
                        $temprow_for_suborder[$key]['multiple_ending_time'] = $sub_row['ending_time'];
                    }
                    $tempRow['multiple_days_booking'] = $temprow_for_suborder;
                }
                if (!empty($sub_order_record)) {
                    $tempRow['new_end_time_with_date'] = date("d-M-Y", strtotime($sub_order_record[0]['date_of_service'])) . ' ' . date("h:i A", strtotime($sub_order_record[0]['ending_time']));
                }
                $tempRow['invoice_no'] = 'INV-' . $row['id'];
                $is_already_exist_query = fetch_details('enquiries', ['customer_id' => $row['user_id'], 'booking_id' => $row['id']]);
                if (empty($is_already_exist_query)) {
                    $e_id = "";
                } else {
                    $e_id = $is_already_exist_query[0]['id'];
                }
                $tempRow['e_id'] = $e_id;
                if (!$from_app) {
                    $tempRow['operations'] = $operations;
                    unset($tempRow['updated_at']);
                }
                $rows[] = $tempRow;
            }
        }
        // Rebuild address strings from custom fields.
        Addresses_model::rebuildAddressOnOrderRows($rows);

        $bulkData['rows'] = $rows;
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            return json_encode($bulkData);
        }
    }
    /**
     * Snapshot a service's title in every language it has translations for, at the
     * moment of order placement. Order line items must keep showing the title as it
     * existed when the order was placed, not whatever the service is renamed to later.
     *
     * @return string|null JSON map of language_code => title, or null when no translations exist
     */
    public function buildServiceTitleTranslationsSnapshot(int $serviceId): ?string
    {
        $translationModel = new \App\Models\TranslatedServiceDetails_model();
        $translations = $translationModel->getAllTranslationsForService($serviceId);

        $titles = [];
        foreach ($translations as $translation) {
            if (!empty($translation['title'])) {
                $titles[$translation['language_code']] = $translation['title'];
            }
        }

        if (empty($titles)) {
            return null;
        }

        return json_encode($titles);
    }

    /**
     * Resolve a service_title for display, preferring the per-language snapshot taken
     * at order time over the single base column. Editing a service's translations later
     * must never change how a past order displays.
     */
    public function resolveServiceTitleFromSnapshot(array $orderService, string $languageCode): string
    {
        $snapshot = $orderService['service_title_translations'] ?? null;
        if (!empty($snapshot)) {
            $titles = json_decode($snapshot, true);
            if (is_array($titles)) {
                $defaultLang = get_default_language();
                return $titles[$languageCode]
                    ?? $titles[$defaultLang]
                    ?? (reset($titles) ?: ($orderService['service_title'] ?? ''));
            }
        }

        return $orderService['service_title'] ?? '';
    }

    public function invoice($order_id)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('orders o');
        $tempRow = array();
        $builder->select('o.*,u.username as customer,u.phone as customer_no,u.email as customer_email,up.username as partner_name,up.phone as partner_no,u.balance as user_wallet,
        o.visiting_charges,pd.address as partner_address,pd.company_name')
            ->join('order_services os', 'os.order_id=o.id', 'left')
            ->join('users u', 'u.id=o.user_id', 'left')
            ->join('services s', 's.id=os.service_id', 'left')
            ->join('users up', 'up.id=o.partner_id', 'left')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id', 'left');
        $builder->where('o.id', $order_id)->where("os.status != 'cancelled'");
        $order_record = $builder->get()->getResultArray();
        foreach ($order_record as $row) {
            $builder = $db->table('order_services os');
            // Use tax_type from order_services (os.* includes os.tax_type); do not select s.tax_type
            $services = $builder->select('os.*,s.tags,s.duration,s.category_id,s.image as service_image')
                ->where('os.order_id', $row['id'])
                ->join('services as s', 's.id=os.service_id', 'left')->get()->getResultArray();

            // Resolve service_title from the per-order-time translation snapshot for the
            // current panel/session language, falling back to the base snapshot column.
            $currentLanguage = get_current_language();
            foreach ($services as &$service) {
                $service['service_title'] = $this->resolveServiceTitleFromSnapshot($service, $currentLanguage);
            }
            unset($service);

            $tempRow['order'] = $order_record[0];
            $tempRow['order']['services'] = $services;
        }

        // Rebuild customer address from custom fields.
        if (!empty($tempRow['order'])) {
            $orderRow = [$tempRow['order']];
            Addresses_model::rebuildAddressOnOrderRows($orderRow);
            $tempRow['order'] = $orderRow[0];
        }

        return $tempRow;
    }
    public function ordered_services_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'o.id', $order = 'DESC', $where = [], $where_in_key = '', $where_in_value = [])
    {
        $db = \Config\Database::connect();
        $builder = $db->table('order_services os');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`os.id`' => $search,
                '`os.order_id`' => $search,
                '`os.service_id`' => $search,
                '`os.service_title`' => $search,
                '`os.quantity`' => $search,
                '`os.status`' => $search
            ];
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        $sort = "id";
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'id') {
                $sort = "id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        $order = "ASC";
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if ($from_app) {
            $where['status'] = 1;
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $builder->select('COUNT(os.id) as `total` ')
            ->join('services s', 's.id = os.service_id', 'left');
        $order_count = $builder->get()->getResultArray();
        $total = $order_count[0]['total'];
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        $builder->select('os.*,s.is_cancelable, s.cancelable_till')
            ->join('services s', 's.id = os.service_id', 'left');
        $taxes = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        foreach ($taxes as $row) {
            $tempRow['id'] = $row['id'];
            $tempRow['order_id'] = $row['order_id'];
            $tempRow['service_id'] = $row['service_id'];
            $tempRow['service_title'] = $row['service_title'];
            $tempRow['tax_percentage'] = $row['tax_percentage'];
            $tempRow['tax_amount'] = $row['tax_amount'];
            $tempRow['price'] = $row['price'];
            $tempRow['quantity'] = $row['quantity'];
            $tempRow['sub_total'] = $row['sub_total'];
            $tempRow['is_cancelable'] = ($row['is_cancelable'] == 1) ?
                "<span class='badge badge-success'>Yes</span>" : "<span class='badge badge-danger'>No</span>";
            $tempRow['cancelable_till'] = ($row['cancelable_till'] != '') ? $row['cancelable_till'] : 'Not cancelable';
            // 
            $tempRow['status'] = $row['status'];
            if ($row['is_cancelable'] == 1) {
                if ($row['status'] == 'completed') {
                    $tempRow['operations'] = '';
                } else if ($row['status'] == 'cancelled') {
                    $tempRow['operations'] = '';
                } else {
                    $tempRow['operations'] = '
                    <button type="button" class="btn btn-danger btn-sm cancel_order" title="Cancel Order">
                        <i class="fas fa-times"></i>
                    </button>
                    ';
                }
            } else {
                $tempRow['operations'] = '-';
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        }
    }

    /**
     * Apply translations to order data including partner company name and service details
     * This method adds translated fields based on the requested language
     * 
     * @param array $orderData Order data with services
     * @param string $languageCode Language code for translations
     * @return array Order data with applied translations
     */
    private function applyTranslationsToOrder(array $orderData, string $languageCode = 'en'): array
    {
        try {
            $orderData['translated_status'] = getTranslatedValue($orderData['status'], 'panel');

            // Apply partner company name translation if partner_id exists
            if (isset($orderData['partner_id']) && !empty($orderData['partner_id'])) {
                $defaultLang = get_default_language();

                // Fetch all partner translations for this partner
                $allPartnerTranslations = $this->getAllPartnerTranslations($orderData['partner_id']);

                // Update company_name with default language fallback
                $orderData['company_name'] = $this->getCompanyNameWithFallback($orderData, $defaultLang, $allPartnerTranslations);

                // Update translated_company_name with requested language fallback
                $orderData['translated_company_name'] = $this->getTranslatedCompanyNameWithFallback($orderData, $languageCode, $defaultLang, $allPartnerTranslations);

                // Update translated_username with requested language fallback
                $orderData['translated_username'] = $this->getTranslatedUsernameWithFallback($orderData, $languageCode, $defaultLang, $allPartnerTranslations);
            }

            // Apply service translations if services exist
            if (!empty($orderData['services']) && is_array($orderData['services'])) {
                $defaultLang = get_default_language();

                // OPTIMIZATION: Collect all service IDs and fetch all translations in a single query
                $serviceIds = [];
                foreach ($orderData['services'] as $service) {
                    if (!empty($service['service_id'])) {
                        $serviceIds[] = $service['service_id'];
                    }
                }

                // Fetch all translations for all services in one query
                $allServiceTranslations = [];
                if (!empty($serviceIds)) {
                    $translationModel = new \App\Models\TranslatedServiceDetails_model();
                    $allServiceTranslations = $translationModel->getAllTranslationsForMultipleServices($serviceIds);
                }

                foreach ($orderData['services'] as &$service) {
                    if (empty($service['service_id']))
                        continue;

                    // Get translations for this specific service from the bulk-fetched data
                    $serviceTranslations = $allServiceTranslations[$service['service_id']] ?? [];

                    $service['translated_status'] = getTranslatedValue($service['status'], 'panel');

                    // Closure to resolve fallback for any field using the pre-fetched translations
                    $resolveField = function ($field, $originalField = null) use ($serviceTranslations, $languageCode, $defaultLang, $service) {
                        // If translations array is empty, fallback immediately to original field
                        if (empty($serviceTranslations)) {
                            return $originalField ? ($service[$originalField] ?? '') : ($service[$field] ?? '');
                        }

                        $current = null;
                        $default = null;
                        $firstAvailable = null;

                        // Loop through all language translations for this service
                        foreach ($serviceTranslations as $languageCodeKey => $translation) {
                            if ($firstAvailable === null && !empty($translation[$field])) {
                                $firstAvailable = $translation[$field];
                            }
                            if ($languageCodeKey === $languageCode && !empty($translation[$field])) {
                                $current = $translation[$field];
                            }
                            if ($languageCodeKey === $defaultLang && !empty($translation[$field])) {
                                $default = $translation[$field];
                            }
                        }

                        // Apply fallback chain: current language → default language → first available → original field
                        return $current
                            ?? $default
                            ?? $firstAvailable
                            ?? ($originalField ? ($service[$originalField] ?? '') : ($service[$field] ?? ''));
                    };

                    // Apply translations with fallback for all required fields
                    // For service_title: use default language translation from snapshot
                    $service['service_title'] = $this->resolveServiceTitleFromSnapshot($service, $defaultLang);

                    // For translated_title: use requested language translation from snapshot
                    $service['translated_title'] = $this->resolveServiceTitleFromSnapshot($service, $languageCode);

                    // For title field: use the same logic as translated_title
                    $service['title'] = $service['translated_title'];

                    // Apply same logic to all other service fields
                    $service['description'] = $this->getServiceFieldWithFallback($service, 'description', $defaultLang, $serviceTranslations);
                    $service['translated_description'] = $this->getTranslatedServiceFieldWithFallback($service, 'description', $languageCode, $defaultLang, $serviceTranslations);

                    $service['long_description'] = $this->getServiceFieldWithFallback($service, 'long_description', $defaultLang, $serviceTranslations);
                    $service['translated_long_description'] = $this->getTranslatedServiceFieldWithFallback($service, 'long_description', $languageCode, $defaultLang, $serviceTranslations);

                    $service['tags'] = $this->getServiceFieldWithFallback($service, 'tags', $defaultLang, $serviceTranslations);
                    $service['translated_tags'] = $this->getTranslatedServiceFieldWithFallback($service, 'tags', $languageCode, $defaultLang, $serviceTranslations);

                    $service['faqs'] = $this->getServiceFieldWithFallback($service, 'faqs', $defaultLang, $serviceTranslations);
                    $service['translated_faqs'] = $this->getTranslatedServiceFieldWithFallback($service, 'faqs', $languageCode, $defaultLang, $serviceTranslations);
                }
                unset($service); // break reference
            }

            return $orderData;
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Error applying translations to order: ' . $e->getMessage());
            return $orderData; // Return original data if translation fails
        }
    }

    /**
     * Get all partner translations for a specific partner
     * Returns all language translations indexed by language code
     * 
     * @param int $partnerId Partner ID
     * @return array All partner translations indexed by language code
     */
    private function getAllPartnerTranslations(int $partnerId): array
    {
        try {
            $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            $allTranslations = $translationModel->getAllTranslationsForPartner($partnerId);

            // Index results by language code for efficient lookup
            $translations = [];
            foreach ($allTranslations as $translation) {
                $languageCode = $translation['language_code'];
                $translations[$languageCode] = $translation;
            }

            return $translations;
        } catch (\Exception $e) {
            log_message('error', 'Error getting all partner translations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current language from request headers
     * 
     * @return string Language code (defaults to 'en')
     */
    private function getCurrentLanguageFromRequest(): string
    {
        return get_current_language_from_request();
    }

    /**
     * Resolve cancellation reason with multilingual fallback.
     * Priority: requested language → default language translation → base reasons.reason.
     */
    private function resolveCancelReason($cancelReasonId, string $languageCode): array
    {
        $result = ['id' => null, 'reason' => null];

        if (empty($cancelReasonId)) {
            return $result;
        }

        $db = \Config\Database::connect();

        $base = $db->table('reasons')
            ->select('id, reason')
            ->where('id', (int) $cancelReasonId)
            ->get()
            ->getRowArray();

        if (empty($base)) {
            return ['id' => (int) $cancelReasonId, 'reason' => null];
        }

        $defaultLang = get_default_language();
        $resolved = $base['reason'];

        if ($db->tableExists('translated_reasons')) {
            $translations = $db->table('translated_reasons tr')
                ->select('tr.reason, l.code as language_code')
                ->join('languages l', 'l.id = tr.language_id')
                ->where('tr.reason_id', (int) $cancelReasonId)
                ->whereIn('l.code', array_values(array_unique([$languageCode, $defaultLang])))
                ->get()
                ->getResultArray();

            $byLang = [];
            foreach ($translations as $t) {
                $byLang[$t['language_code']] = $t['reason'];
            }

            if (!empty($byLang[$languageCode])) {
                $resolved = $byLang[$languageCode];
            } elseif (!empty($byLang[$defaultLang])) {
                $resolved = $byLang[$defaultLang];
            }
        }

        return [
            'id' => (int) $cancelReasonId,
            'reason' => $resolved,
        ];
    }

    /**
     * Get service title with fallback for default language
     * Priority: default language translation → main table service_title → first available translation
     * 
     * @param array $service Service data
     * @param string $defaultLang Default language code
     * @param array $serviceTranslations All translations for this service
     * @return string Service title with fallback
     */
    private function getServiceTitleWithFallback(array $service, string $defaultLang, array $serviceTranslations): string
    {
        // If no translations available, use main table service_title
        if (empty($serviceTranslations)) {
            return $service['service_title'] ?? '';
        }

        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this service
        foreach ($serviceTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation['title'])) {
                $firstAvailable = $translation['title'];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation['title'])) {
                $defaultTranslation = $translation['title'];
            }
        }

        // Apply fallback chain: default language → main table → first available
        return $defaultTranslation
            ?? ($service['service_title'] ?? '')
            ?? $firstAvailable
            ?? '';
    }

    /**
     * Get translated service title with fallback for requested language
     * Priority: requested language → default language → main table service_title → first available translation
     * 
     * @param array $service Service data
     * @param string $languageCode Requested language code
     * @param string $defaultLang Default language code
     * @param array $serviceTranslations All translations for this service
     * @return string Translated service title with fallback
     */
    private function getTranslatedServiceTitleWithFallback(array $service, string $languageCode, string $defaultLang, array $serviceTranslations): string
    {
        // If no translations available, use main table service_title
        if (empty($serviceTranslations)) {
            return $service['service_title'] ?? '';
        }

        $currentTranslation = null;
        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this service
        foreach ($serviceTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation['title'])) {
                $firstAvailable = $translation['title'];
            }
            if ($languageCodeKey === $languageCode && !empty($translation['title'])) {
                $currentTranslation = $translation['title'];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation['title'])) {
                $defaultTranslation = $translation['title'];
            }
        }

        // Apply fallback chain: requested language → default language → main table → first available
        return $currentTranslation
            ?? $defaultTranslation
            ?? ($service['service_title'] ?? '')
            ?? $firstAvailable
            ?? '';
    }

    /**
     * Get company name with fallback for default language
     * Priority: default language translation → main table company_name → first available translation
     * 
     * @param array $orderData Order data
     * @param string $defaultLang Default language code
     * @param array $partnerTranslations All translations for this partner
     * @return string Company name with fallback
     */
    private function getCompanyNameWithFallback(array $orderData, string $defaultLang, array $partnerTranslations): string
    {
        // If no translations available, use main table company_name
        if (empty($partnerTranslations)) {
            return $orderData['company_name'] ?? '';
        }

        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this partner
        foreach ($partnerTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation['company_name'])) {
                $firstAvailable = $translation['company_name'];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation['company_name'])) {
                $defaultTranslation = $translation['company_name'];
            }
        }

        // Apply fallback chain: default language → main table → first available
        return $defaultTranslation
            ?? ($orderData['company_name'] ?? '')
            ?? $firstAvailable
            ?? '';
    }

    /**
     * Get translated company name with fallback for requested language
     * Priority: requested language → default language → main table company_name → first available translation
     * 
     * @param array $orderData Order data
     * @param string $languageCode Requested language code
     * @param string $defaultLang Default language code
     * @param array $partnerTranslations All translations for this partner
     * @return string Translated company name with fallback
     */
    private function getTranslatedCompanyNameWithFallback(array $orderData, string $languageCode, string $defaultLang, array $partnerTranslations): string
    {
        // If no translations available, use main table company_name
        if (empty($partnerTranslations)) {
            return $orderData['company_name'] ?? '';
        }

        $currentTranslation = null;
        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this partner
        foreach ($partnerTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation['company_name'])) {
                $firstAvailable = $translation['company_name'];
            }
            if ($languageCodeKey === $languageCode && !empty($translation['company_name'])) {
                $currentTranslation = $translation['company_name'];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation['company_name'])) {
                $defaultTranslation = $translation['company_name'];
            }
        }

        // Apply fallback chain: requested language → default language → main table → first available
        return $currentTranslation
            ?? $defaultTranslation
            ?? ($orderData['company_name'] ?? '')
            ?? $firstAvailable
            ?? '';
    }

    /**
     * Get translated username with fallback chain
     * Priority: requested language → default language → main table username → first available translation
     * 
     * @param array $orderData Order data containing partner information
     * @param string $languageCode Requested language code
     * @param string $defaultLang Default language code
     * @param array $partnerTranslations All translations for this partner
     * @return string Translated username with fallback
     */
    private function getTranslatedUsernameWithFallback(array $orderData, string $languageCode, string $defaultLang, array $partnerTranslations): string
    {
        // If no translations available, use main table username
        if (empty($partnerTranslations)) {
            return $orderData['partner_name'] ?? '';
        }

        $currentTranslation = null;
        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this partner
        foreach ($partnerTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation['username'])) {
                $firstAvailable = $translation['username'];
            }
            if ($languageCodeKey === $languageCode && !empty($translation['username'])) {
                $currentTranslation = $translation['username'];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation['username'])) {
                $defaultTranslation = $translation['username'];
            }
        }

        // Apply fallback chain: requested language → default language → main table → first available
        return $currentTranslation
            ?? $defaultTranslation
            ?? ($orderData['partner_name'] ?? '')
            ?? $firstAvailable
            ?? '';
    }

    /**
     * Get service field with fallback for default language
     * Priority: default language translation → main table field → first available translation
     * 
     * @param array $service Service data
     * @param string $field Field name to get translation for
     * @param string $defaultLang Default language code
     * @param array $serviceTranslations All translations for this service
     * @return string Field value with fallback
     */
    private function getServiceFieldWithFallback(array $service, string $field, string $defaultLang, array $serviceTranslations): string
    {
        // If no translations available, use main table field
        if (empty($serviceTranslations)) {
            return $service[$field] ?? '';
        }

        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this service
        foreach ($serviceTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation[$field])) {
                $firstAvailable = $translation[$field];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation[$field])) {
                $defaultTranslation = $translation[$field];
            }
        }

        // Apply fallback chain: default language → main table → first available
        return $defaultTranslation
            ?? ($service[$field] ?? '')
            ?? $firstAvailable
            ?? '';
    }

    /**
     * Get translated service field with fallback for requested language
     * Priority: requested language → default language → main table field → first available translation
     * 
     * @param array $service Service data
     * @param string $field Field name to get translation for
     * @param string $languageCode Requested language code
     * @param string $defaultLang Default language code
     * @param array $serviceTranslations All translations for this service
     * @return string Translated field value with fallback
     */
    private function getTranslatedServiceFieldWithFallback(array $service, string $field, string $languageCode, string $defaultLang, array $serviceTranslations): string
    {
        // If no translations available, use main table field
        if (empty($serviceTranslations)) {
            return $service[$field] ?? '';
        }

        $currentTranslation = null;
        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this service
        foreach ($serviceTranslations as $languageCodeKey => $translation) {
            if ($firstAvailable === null && !empty($translation[$field])) {
                $firstAvailable = $translation[$field];
            }
            if ($languageCodeKey === $languageCode && !empty($translation[$field])) {
                $currentTranslation = $translation[$field];
            }
            if ($languageCodeKey === $defaultLang && !empty($translation[$field])) {
                $defaultTranslation = $translation[$field];
            }
        }

        // Apply fallback chain: requested language → default language → main table → first available
        return $currentTranslation
            ?? $defaultTranslation
            ?? ($service[$field] ?? '')
            ?? $firstAvailable
            ?? '';
    }


    public function dashboardRecentBookingList($limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $where_in_key = '', $where_in_value = [])
    {
        $fileService = service('fileService');

        $db = \Config\Database::connect();
        $builder = $db->table('orders o');
        $bulkData = $rows = $tempRow = [];
        $builder
            ->select('o.id, o.status, o.partner_id, o.user_id, o.date_of_service, o.starting_time, o.ending_time, o.final_total,
            up.email as partner_email,up.phone as partner_phone,up.image as provider_profile_image,up.username as partner_name,
            u.id as customer_id, u.username as user_name,
            pd.company_name')
            ->join('users u', 'u.id = o.user_id')
            ->join('users up', 'up.id = o.partner_id')
            ->join('partner_details pd', 'o.partner_id = pd.partner_id');

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        $builder->where('o.parent_id', null);
        $order_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->groupBy('o.id')->get()->getResultArray();
        $bulkData = array();
        // $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();

        if (empty($order_record)) {
            $bulkData = array();
        } else {
            // Use PermissionService (cached) instead of raw DB + get_permission().
            foreach ($order_record as $index_for_order => $row) {
                $operations = '';
                // Use isAdminPanel so super-admins get admin URLs too.
                if ($this->permissionService->can($this->admin_id, 'read', 'orders')) {
                    $base_url = base_url();
                    if ($this->isAdminPanel) {
                        $operations .= '<a class="btn btn-primary btn-sm" href="' . $base_url . '/admin/orders/veiw_orders/' . $row['id'] . '" title=' . labels('view_the_booking', 'View the Booking') . '><i class="fa fa-eye" aria-hidden="true"></i></a>';
                    } else {
                        $operations .= '<a class="btn btn-primary btn-sm" href="' . $base_url . '/partner/orders/veiw_orders/' . $row['id'] . '" title=' . labels('view_the_booking', 'View the Booking') . '><i class="fa fa-eye" aria-hidden="true"></i></a>';
                    }
                }

                if (($row['status'] == 'awaiting')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('awaiting', 'Awaiting') . " 
                    </div>";
                } elseif (($row['status'] == 'confirmed')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('confirmed', 'Confirmed') . "
                    </div>";
                } elseif (($row['status'] == 'rescheduled')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('rescheduled', 'Rescheduled') . "
                    </div>";
                } elseif (($row['status'] == 'cancelled')) {
                    $status = " <div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('cancelled', 'Cancelled') . "
                    </div>";
                } elseif (($row['status'] == 'completed')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('completed', 'Completed') . "
                    </div>";
                } elseif (($row['status'] == 'pending')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('pending', 'Pending') . "
                    </div>";
                } elseif (($row['status'] == 'started')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('started', 'Started') . "
                    </div>";
                } elseif (($row['status'] == 'on_the_way')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-blue text-emerald-blue dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('on_the_way', 'On The Way') . "
                    </div>";
                } elseif (($row['status'] == 'arrived')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-purple text-emerald-purple dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('arrived', 'Arrived') . "
                    </div>";
                } elseif (($row['status'] == 'booking_ended')) {
                    $status = "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('booking_ended', 'Booking Ended') . "
                    </div>";
                } else {
                    $status = labels('undefined_status', 'Status Not Defined');
                }

                $tempRow['id'] = $row['id'];
                // $tempRow['slug'] = 'inv-' . $row['id'];
                $tempRow['customer'] = $row['user_name'];
                $tempRow['customer_id'] = $row['customer_id'];
                // $tempRow['payment_method'] = $row['payment_method'];
                // $tempRow['payment_status'] = $row['payment_status'];

                // Get provider name with language fallback: current language → default language → base table
                // Priority: current language translation → default language translation → base table company_name
                $providerName = $row['partner_name']; // Default fallback to partner_name from query
                if (!empty($row['partner_id'])) {
                    $currentLang = get_current_language();
                    $defaultLang = get_default_language();

                    // Get translated partner details
                    $translatedPartnerModel = new \App\Models\TranslatedPartnerDetails_model();
                    $allTranslations = $translatedPartnerModel->getAllTranslationsForPartner($row['partner_id']);

                    if (!empty($allTranslations)) {
                        $currentTranslation = null;
                        $defaultTranslation = null;

                        // Organize translations by language code
                        $translationsByLang = [];
                        foreach ($allTranslations as $translation) {
                            $translationsByLang[$translation['language_code']] = $translation;
                        }

                        // Try current language first
                        if (!empty($translationsByLang[$currentLang]['company_name'])) {
                            $providerName = $translationsByLang[$currentLang]['company_name'];
                        } elseif (!empty($translationsByLang[$defaultLang]['company_name'])) {
                            // Fallback to default language
                            $providerName = $translationsByLang[$defaultLang]['company_name'];
                        } elseif (!empty($row['company_name'])) {
                            // Final fallback to base table
                            $providerName = $row['company_name'];
                        }
                    } elseif (!empty($row['company_name'])) {
                        // If no translations exist, use base table company_name
                        $providerName = $row['company_name'];
                    }
                }

                $imageSrc = $fileService->url($row['provider_profile_image'], 'profile');

                $profile = '<div class="o-media o-media--middle">
                            <a href="' . $imageSrc . '" data-lightbox="image-1">
                                <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $providerName . '">
                            </a>';
                $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                    <div class="provider_name_table" >' . $providerName . '</span></div>
                    <div class="provider_email_table">' . $row['company_name'] . '</div>
                    <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                    </div>
                    </div></a>';

                $tempRow['partner'] = $profile;
                $tempRow['user_id'] = $row['user_id'];
                $tempRow['partner_id'] = $row['partner_id'];

                $tempRow['status'] = $status;
                $tempRow['new_start_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date(($row['starting_time']), 'h:i A');
                $tempRow['new_end_time_with_date'] = format_date($row['date_of_service'], 'd-m-Y') . ' ' . format_date(($row['ending_time']), 'h:i A');
                $tempRow['date_of_service'] = $row['date_of_service'];
                $tempRow['final_total'] = (str_replace(',', '', $row['final_total']));
                $tempRow['operations'] = $operations;

                // print_r($tempRow); die;
                $rows[] = $tempRow;
            }
        }
        $bulkData['rows'] = $rows;
        return json_encode($bulkData);
    }

    // ================================================================
    //  PRIVATE HELPERS — operations column HTML
    // ================================================================

    /**
     * Build the "operations" dropdown HTML for a single order row.
     *
     * Centralises the admin-vs-partner branching that was previously
     * scattered across multiple isAdmin() checks. Uses $this->isAdminPanel
     * (set once in the constructor) so super-admins are handled correctly.
     *
     * Admin-panel users get: View Booking + Invoice (if completed).
     * Partner-panel users get: View Booking + Delete + Chat.
     *
     * @param  array  $row  A single order row from the query result.
     * @return string       The HTML string for the operations column.
     */
    private function buildOperationsHtml(array $row): string
    {
        $base_url = base_url();

        // Dropdown wrapper — shared by both admin and partner views.
        $html = '<div class="dropdown">'
            . '<a href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
            . '<button class="btn btn-secondary btn-sm px-3"><i class="fas fa-ellipsis-v"></i></button>'
            . '</a>'
            . '<div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';

        if ($this->isAdminPanel) {
            // --- Admin / Super-admin operations ---

            // View booking link (requires read permission).
            if ($this->permissionService->can($this->admin_id, 'read', 'orders')) {
                $html .= '<a class="dropdown-item" href="' . $base_url . 'admin/orders/veiw_orders/' . $row['id'] . '">'
                    . '<i class="fa fa-eye text-primary mr-1" aria-hidden="true"></i>'
                    . labels('view_the_booking', 'View the Booking')
                    . '</a>';
            }

            // Invoice link (requires read permission + completed status).
            if ($row['status'] === 'completed' && $this->permissionService->can($this->admin_id, 'read', 'orders')) {
                $html .= '<a class="dropdown-item" href="' . $base_url . 'admin/orders/invoice/' . $row['id'] . '">'
                    . '<i class="fa fa-receipt text-success mr-1"></i>'
                    . labels('invoice', 'Invoice')
                    . '</a>';
            }

            // Delete booking link for admin/super-admin (requires delete permission).
            // Super-admins pass this automatically via PermissionService.
            if ($this->permissionService->can($this->admin_id, 'delete', 'orders')) {
                $html .= '<a class="dropdown-item delete_orders" data-id="' . $row['id'] . '" '
                    . 'onclick="order_id(this)" data-toggle="modal" data-target="#delete_modal">'
                    . '<i class="fa fa-trash text-danger mr-1"></i>'
                    . labels('delete_booking', 'Delete Booking')
                    . '</a>';
            }
        } else {
            // --- Partner operations ---

            // View booking link (no extra permission gate for partners).
            $html .= '<a class="dropdown-item" href="' . $base_url . 'partner/orders/veiw_orders/' . $row['id'] . '">'
                . '<i class="fa fa-eye text-primary mr-1" aria-hidden="true"></i>'
                . labels('view_the_booking', 'View the Booking')
                . '</a>';

            // Partners cannot delete orders. Only admin-panel users can
            // (handled in the isAdminPanel branch above).

            // Chat link (always available for partners).
            // NOTE:
            // We avoid inline JS (onclick) here because GlobalSanitizer strips it.
            // Instead, we rely on the Bootstrap-table `orders_events` handler in `partner.js`,
            // which receives the full `row` object (including id / partner_id / user_id)
            // and triggers `openBookingChat(...)` from there.
            $html .= '<a class="dropdown-item open-booking-chat" href="#">'
                . '<i class="fas fa-comment-alt text-info"></i>  '
                . labels('chat', 'Chat')
                . '</a>';
        }

        $html .= '</div></div>';
        return $html;
    }

    public function bookingCountsForPartner(int $partnerId, string $today, string $tomorrow, string $dayAfterTomorrow): array
    {
        return [
            'today_bookings' => (int) $this->builder()
                ->join('order_services os', 'os.order_id = orders.id')
                ->join('services s', 's.id = os.service_id')
                ->where('orders.partner_id', $partnerId)
                ->whereIn('orders.status', ['awaiting', 'confirmed', 'rescheduled'])
                ->where('orders.date_of_service >=', $today)
                ->where('orders.date_of_service <', $tomorrow)
                ->countAllResults(),
            'tommorrow_bookings' => (int) $this->builder()
                ->join('order_services os', 'os.order_id = orders.id')
                ->join('services s', 's.id = os.service_id')
                ->where('orders.partner_id', $partnerId)
                ->whereIn('orders.status', ['awaiting', 'confirmed', 'rescheduled'])
                ->where('orders.date_of_service >=', $tomorrow)
                ->where('orders.date_of_service <', $dayAfterTomorrow)
                ->countAllResults(),
            'upcoming_bookings' => (int) $this->builder()
                ->join('order_services os', 'os.order_id = orders.id')
                ->join('services s', 's.id = os.service_id')
                ->where('orders.partner_id', $partnerId)
                ->whereIn('orders.status', ['awaiting', 'confirmed', 'rescheduled'])
                ->where('orders.date_of_service >=', $dayAfterTomorrow)
                ->countAllResults(),
        ];
    }

    public function unsettledAwaitingTotalForPartner(int $partnerId): float
    {
        $row = $this->builder()
            ->selectSum('final_total', 'total')
            ->where([
                'partner_id' => $partnerId,
                'is_commission_settled' => '0',
                'status' => 'awaiting',
            ])
            ->get()->getRowArray();

        return (float) ($row['total'] ?? 0);
    }

    public function monthlySalesForPartner(int $partnerId, int $months): array
    {
        return $this->builder()
            ->select('YEAR(date_of_service) as year, MONTHNAME(date_of_service) as month, SUM(final_total) as total_amount')
            ->where("date_of_service >= DATE_SUB(CURDATE(), INTERVAL " . (int) $months . " MONTH)", null, false)
            ->where('date_of_service <=', date('Y-m-d'))
            ->where(['partner_id' => $partnerId, 'status' => 'completed'])
            ->groupBy('YEAR(date_of_service), MONTH(date_of_service)')
            ->orderBy('YEAR(date_of_service), MONTH(date_of_service)')
            ->get()->getResultArray();
    }

    public function yearlySalesForPartner(int $partnerId): array
    {
        return $this->builder()
            ->select('YEAR(date_of_service) as year, SUM(final_total) as total_amount')
            ->where('date_of_service BETWEEN CURDATE() - INTERVAL 1 YEAR AND CURDATE()')
            ->where(['partner_id' => $partnerId, 'date_of_service < ' => date('Y-m-d H:i:s'), 'status' => 'completed'])
            ->groupBy('YEAR(date_of_service)')
            ->get()->getResultArray();
    }

    public function weeklySalesForPartner(int $partnerId): array
    {
        return $this->builder()
            ->select('WEEK(date_of_service) as week, SUM(final_total) as total_amount')
            ->where('date_of_service BETWEEN CURDATE() - INTERVAL 1 WEEK AND CURDATE()')
            ->where(['partner_id' => $partnerId, 'date_of_service < ' => date('Y-m-d H:i:s'), 'status' => 'completed'])
            ->groupBy('WEEK(date_of_service)')
            ->get()->getResultArray();
    }
}
