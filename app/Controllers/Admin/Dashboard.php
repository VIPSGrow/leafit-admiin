<?php

namespace App\Controllers\Admin;

use App\Models\Admin_contact_query;
use App\Models\Orders_model;
use App\Models\Partners_model;
use App\Models\Users_model;
use App\Services\utility\FileService;
use DateTime;
use DateTimeZone;

class Dashboard extends Admin
{

    protected Users_model $user_model;
    protected Orders_model $orders;
    protected Partners_model $partner;
    protected FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->user_model = new \App\Models\Users_model();
        $this->orders = new \App\Models\Orders_model();
        $this->partner = new \App\Models\Partners_model();
        $this->fileService = new FileService();
        helper('ResponceServices');
        helper('function');
    }
    public function cancle_elapsed_time_order()
    {
        try {
            $currentDate = date('Y-m-d');
            $currentTimestamp = time();
            $currentTime = date('H:i', $currentTimestamp);
            $prepaid_orders = fetch_details('orders', ['status' => 'awaiting', 'payment_status' => 0, 'date_of_service' => $currentDate]);
            $setting = get_settings('general_settings', true);
            $prepaid_booking_cancellation_time = (isset($setting['prepaid_booking_cancellation_time'])) ? intval($setting['prepaid_booking_cancellation_time']) : "30";
            foreach ($prepaid_orders as $order) {
                $serviceTime = strtotime($order['starting_time']);
                $checkTime = $serviceTime - ($prepaid_booking_cancellation_time * 60); // 1800 seconds = 30 minutes
                if ($checkTime <= strtotime($currentTime)) {
                    verify_transaction($order['id']);
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - cancle_elapsed_time_order()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update_subscription_status()
    {
        try {
            $db = \Config\Database::connect();

            $lock = $db->query("SELECT GET_LOCK('subscription_cron_lock', 5) AS l")
                ->getRow()
                ->l;

            if (!$lock) {
                return; // another cron is running
            }

            try {
                $now = new DateTime('now', new DateTimeZone('UTC'));
                $subscriptions = $db->table('partner_subscriptions ps')
                    ->select('ps.*, u.username, u.email')
                    ->join('users u', 'u.id = ps.partner_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ps.status', 'active')
                    ->where('ps.duration !=', 'unlimited')
                    ->where('ug.group_id', 3)
                    ->get()
                    ->getResultArray();

                foreach ($subscriptions as $sub) {
                    $deactivationReason = null;

                    // -------- ORDER LIMIT CHECK --------
                    if ($sub['order_type'] === 'limited') {

                        $count = count_orders_towards_subscription_limit(
                            $sub['partner_id'],
                            $sub['updated_at'],
                            [],
                            $db
                        );

                        if ($count >= $sub['max_order_limit']) {
                            $deactivationReason = 'order_limit';
                        }
                    }


                    // -------- EXPIRY CHECK --------
                    $expiry = new DateTime($sub['expiry_date'], new DateTimeZone('UTC'));
                    $expiry->setTime(23, 59, 59);
                    if (!$deactivationReason && $expiry <= $now) {
                        $deactivationReason = 'expiry';
                    }


                    // -------- APPLY DEACTIVATION --------
                    if ($deactivationReason) {

                        update_details(
                            ['status' => 'deactive'],
                            ['id' => $sub['id'], 'status' => 'active'],
                            'partner_subscriptions'
                        );

                        // ---- SEND NOTIFICATION (IN-CRON) ----
                        try {
                            queue_notification_service(
                                eventType: 'subscription_expired',
                                recipients: ['user_id' => $sub['partner_id']],
                                context: [
                                    'subscription_id' => $sub['subscription_id'] ?? '',
                                    'subscription_name' => $sub['name'] ?? 'Subscription',
                                    'provider_id' => $sub['partner_id'],
                                    'provider_name' => $sub['username'] ?? 'Provider',
                                    'expiry_date' => !empty($sub['expiry_date'])
                                        ? date('d-m-Y', strtotime($sub['expiry_date']))
                                        : '',
                                    'purchase_date' => !empty($sub['purchase_date'])
                                        ? date('d-m-Y', strtotime($sub['purchase_date']))
                                        : '',
                                    'duration' => $sub['duration'] ?? '',
                                    'deactivation_reason' => $deactivationReason,
                                ],
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'],
                                    'platforms' => ['android', 'ios', 'provider_panel'],
                                    'type' => 'subscription',
                                ]
                            );
                        } catch (\Throwable $e) {
                            log_message('error', '[SUBSCRIPTION_CRON] ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', '[CRON ERROR] ' . $e->getMessage());
                log_message('error', '[CRON ERROR FILE] ' . $e->getFile() . ':' . $e->getLine());
            } finally {
                $db->query("SELECT RELEASE_LOCK('subscription_cron_lock')");
            }

            $currentDate = date('Y-m-d');
            $currentTimestamp = time();
            $currentTime = date('H:i', $currentTimestamp);
            //booking auto cancellation
            $orders = fetch_details('orders', ['status' => 'awaiting', 'date_of_service' => $currentDate]);
            $setting = get_settings('general_settings', true);
            $booking_auto_cancle = (isset($setting['booking_auto_cancle_duration'])) ? intval($setting['booking_auto_cancle_duration']) : "30";
            foreach ($orders as $order) {
                $serviceTime = strtotime($order['starting_time']);
                $checkTime = $serviceTime - ($booking_auto_cancle * 60); // 1800 seconds = 30 minutes
                if ($checkTime <= strtotime($currentTime)) {
                    // Get current status before cancellation (needed for notifications)
                    $active_status = $order['status']; // This will be 'awaiting'

                    // Process refund for auto-cancelled booking
                    $data = process_refund($order['id'], 'cancelled', $order['user_id']);
                    update_details(['status' => 'cancelled'], ['id' => $order['id']], 'orders');

                    // Send email notifications to customer when booking is auto-cancelled
                    // This ensures customers are notified via email, SMS, and FCM when their booking is automatically cancelled
                    $translated_status = labels('cancelled', 'Cancelled');
                    $languageCode = get_default_language();
                    // No specific user_id for auto-cancellation (system-initiated)
                    send_booking_status_notifications($order['id'], 'cancelled', $translated_status, $active_status, $languageCode, null);
                }
            }

            //------------------------- Promo Code Auto-Expiration -------------------------
            try {
                $promo_code_model = new \App\Models\Promo_code_model();
                $promo_code_model->expireEndedPromoCodes();
            } catch (\Throwable $e) {
                log_message('error', '[PROMO_CODE_CRON] ' . $e->getMessage());
            }

            //------------------------- Custom Job Requests Auto-Cancellation -------------------------
            $custom_jobs = fetch_details('custom_job_requests', ['status !=' => 'booked']);
            $currentTimestamp = date('Y-m-d H:i:s');


            foreach ($custom_jobs as $job) {
                $jobEndDateTime = $job['requested_end_date'] . ' ' . $job['requested_end_time'];
                // if ($currentTimestamp == "11-59 PM") {
                if ($jobEndDateTime <= $currentTimestamp) {
                    // Initialize $data as a new array to avoid reusing previous $data from process_refund()
                    // This prevents 'error' and other keys from being included in the update
                    $data = [];
                    $data['status'] = 'cancelled';
                    $where = [];
                    $where['id'] = $job['id'];
                    $where['status !='] = "cancelled";

                    update_details($data, $where, 'custom_job_requests');
                    // log_message('error', 'custom_job_requests expired and updated to cancelled');
                }
                // }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - update_subscription_status()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function index()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                // one-time script: safely delete only configured legacy directories
                // the script itself makes sure each target runs only once
                \App\Scripts\OneTimeCleanup::run();

                $db = \Config\Database::connect();
                $total_users = $db->table('users u')->select('count(u.id) as `total`')->get()->getResultArray()[0]['total'];


                $total_customers = $db->table('users u')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', 2)
                    ->select('COUNT(u.id) as total')
                    ->get()
                    ->getRowArray()['total'];


                $total_on_sale_service = $db->table('services s')->select('count(s.id) as `total`')->where(['discounted_price >=' => 0])->get()->getResultArray()[0]['total'];
                $this->data['total_on_sale_service'] = $total_on_sale_service;
                // Count orders with the same conditions as the Orders page for consistency
                // Exclude cancelled orders and orders with failed payments (payment_status = 2)
                // Failed payment bookings should not be counted in total booking statistics
                $total_orders = $db->table('orders o')
                    ->select('count(DISTINCT(o.id)) as total')
                    ->join('order_services os', 'os.order_id=o.id')
                    ->join('users u', 'u.id=o.user_id')
                    ->join('users up', 'up.id=o.partner_id')
                    ->join('partner_details pd', 'o.partner_id = pd.partner_id')
                    ->where('o.parent_id IS NULL')
                    ->where('o.status !=', 'cancelled')
                    ->where('(o.payment_status != 2 OR o.payment_status IS NULL)')
                    ->get()->getResultArray()[0]['total'];
                $symbol = get_currency();
                $this->data['total_orders'] = $total_orders;
                $this->data['total_users'] = $total_users;
                $this->data['total_customers'] = $total_customers;

                $this->data['currency'] = $symbol;
                setPageInfo($this->data, labels('Dashboard', 'Dashboard') . ' | ' . labels('admin_panel', 'Admin Panel'), 'dashboard');
                $Partners_model = new Partners_model();
                $limit = 5;
                $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
                $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
                $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
                $where = [];
                $rating_data = $Partners_model->list(true, $search, $limit, $offset, 'number_of_orders', 'desc', $where, 'partner_id', [], '');
                $income_revenue = total_income_revenue();
                $this->data['income_revenue'] = $income_revenue;
                $admin_income_revenue = admin_income_revenue();
                $this->data['admin_income_revenue'] = $admin_income_revenue;
                $provider_income_revenue = provider_income_revenue();
                $this->data['provider_income_revenue'] = $provider_income_revenue;
                $this->data['rating_data'] = $rating_data;
                $rating_wise_rating_data = $Partners_model->list(true, $search, 3, $offset, ' pd.ratings', 'desc', $where, 'pd.partner_id', [], '');
                $this->data['rating_wise_rating_data'] = $rating_wise_rating_data;
                $top_trending_services = $this->top_trending_services();
                $this->data['top_trending_services'] = $top_trending_services;
                $this->data['categories'] = get_categories_with_translated_names();
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, 'app/Controllers/admin/Dashboard.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function top_trending_services()
    {
        try {
            $top_trending_services = fetch_top_trending_services((!empty($this->request->getPost('data_trending_filter'))) ? $this->request->getPost('data_trending_filter') : "null");
            foreach ($top_trending_services as &$service) {
                $service['image'] = $this->fileService->url(
                    $service['image'] ?? null,
                    'services'
                );
            }
            unset($service);
            if ($this->request->isAJAX()) {
                $response = array('error' => false, 'data' => $top_trending_services);
                print_r(json_encode($response));
            } else {
                return $top_trending_services;
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '-->app/Controllers/admin/Dashboard.php  - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function fetch_details()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                $sales[] = array();
                $db = \Config\Database::connect();
                $month_total_earning = $db->table('orders o')
                    ->select('sum(o.final_total) AS total_earning,DATE_FORMAT(created_at,"%b") AS month_name')
                    ->where(['status' => 4])
                    ->groupBy('year(CURDATE()),MONTH(created_at)')
                    ->orderBy('year(CURDATE()),MONTH(created_at)')
                    ->get()->getResultArray();
                $month_wise_earning['total_earning'] = array_map('intval', array_column($month_total_earning, 'total_earning'));
                $month_wise_earning['month_name'] = array_column($month_total_earning, 'month_name');
                $sales = $month_total_earning;
                print_r(json_encode($sales));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - fetch_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            print_r(json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order)));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function save_web_token()
    {
        try {
            $token = $this->request->getPost('token');
            // Get language_code from POST data, or use user's language_code from database, or null
            $languageCode = get_current_language();
            // update_details(['panel_fcm_id' => $token,], ['id' => $user[0]['id']], 'users');
            store_users_fcm_id($this->userId, $token, 'admin_panel', $token, $languageCode);
            print_r(json_encode("admin panel token saved"));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - save_web_token()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    // public function test()
    // {
    //     return view('main_system_settings');
    // }
    public function forgot_password()
    {
        setPageInfo($this->data, labels('commission_settlement', 'Commission Settlement') . ' | ' . labels('admin_panel', 'Admin Panel'), 'manage_commission');
        return view('backend/forgot_password_otp');
    }
    public function recent_orders()
    {
        try {
            $orders_model = new Orders_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 7;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $where = [];
            print_r($orders_model->dashboardRecentBookingList($limit, $offset, $sort, $order, $where));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - recent_orders()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function NotFoundController()
    {
        return view('404');
    }
    public function customer_queris()
    {
        try {
            helper('function');
            $uri = service('uri');
            $db = \Config\Database::connect();
            $symbol = get_currency();
            $this->data['currency'] = $symbol;
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('user_queries', 'User Queries') . ' | ' . labels('admin_panel', 'Admin Panel'), 'customer_query');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - customer_queris()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function customer_queris_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $admin_contact_query = new Admin_contact_query();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $data = $admin_contact_query->list([], 'yes', false, $limit, $offset, $sort, $order, $search);
            return $data;
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - customer_queris_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function upload_media()
    {
        try {
            $path = FCPATH . "/public/uploads/media/";
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $request = \Config\Services::request();
            $files = $request->getFiles();
            $other_image_info_error = "";
            foreach ($files['documents'] as $file) {
                if ($file->isValid() && !$file->hasMoved()) {
                    $newName = $file->getRandomName();
                    if (!$file->move($path, $newName)) {
                        $other_image_info_error .= 'Failed to move file: ' . $file->getErrorString() . "\n";
                    }
                } else {
                    $other_image_info_error .= 'Invalid file: ' . $file->getErrorString() . "\n";
                }
            }
            $response = [];
            if (!empty($other_image_info_error)) {
                $response['error'] = true;
                $response['file_name'] = '';
                $response['message'] = $other_image_info_error;
            } else {
                $response['error'] = false;
                $response['file_name'] = $files['documents'][0]->getName();
                $response['message'] = "Files Uploaded Successfully..!";
            }
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Dashboard.php - upload_media()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function getSQLString()
    {
        $db = \Config\Database::connect();
        $setting = [
            'hero_section' => json_encode([
                'status' => 1,
                'short_headline' => 'OPPORTUNITY KNOCKS',
                'title' => 'We Provide High Quality Professional Services',
                'description' => 'Become an eDemand provider and start earning extra money today. Enjoy flexibility, choose your hours, and take control of your financial future.',
                'images' => [
                    ['image' => '1742990017_b7543a5a607df5412e6b.png'],
                    ['image' => '1742990529_dc811298a4aa05b7cb85.png'],
                    ['image' => '1742990529_5f56f34f54c25c2abf25.png'],
                    ['image' => '1742990529_70d695d2bb7e36041338.png']
                ]
            ]),
            'how_it_work_section' => json_encode([
                'status' => 1,
                'short_headline' => 'HOW IT WORKS',
                'title' => 'Become a Successful Service Provider',
                'description' => 'Easily transform your skills into a thriving business. Our platform provides the tools you need to attract customers, manage bookings, and grow your service empire.',
                'steps' => json_encode([
                    ['title' => 'Create Your Provider Account', 'description' => 'Start by registering on our platform as a service provider.'],
                    ['title' => 'Build Your Service Profile', 'description' => 'Showcase your expertise by adding detailed information about the services.']
                ])
            ]),
            'category_section' => json_encode([
                'status' => 1,
                'short_headline' => 'YOUR NEEDS, OUR SERVICES ',
                'title' => 'Discover a World of Services at Your Fingertips',
                'description' => 'Need a cleaner, a plumber, or a tech expert? We have got you covered. Discover a wide range of services, all in one place.'
            ]),
            'subscription_section' => json_encode([
                'status' => 1,
                'short_headline' => 'UNLOCK UNLIMITED ACCESS',
                'title' => 'Elevate Your Business with Our Subscription',
                'description' => ' Get more out of eDemand with our subscription plan. Enjoy increased visibility, access to premium features, and the ability to expand your service offerings.'
            ]),
            'top_providers_section' => json_encode([
                'status' => 1,
                'short_headline' => 'TOP RATED PROVIDERS',
                'title' => 'Trusted by Thousands: Our Top-Rated Providers',
                'description' => 'Our top-rated providers are customer favorites. With a proven track record of excellence, they consistently deliver outstanding service.'
            ]),
            'review_section' => json_encode([
                'status' => 1,
                'short_headline' => 'YOUR REVIEW MATTERS',
                'title' => 'what our Customers Says About Providers',
                'description' => 'Discover how eDemand has transformed businesses. Hear directly from our satisfied providers about their success stories and how our platform has helped them reach new heights.'
            ]),
            'faq_section' => json_encode([
                'status' => 1,
                'short_headline' => 'TRANSPARENCY MATTERS',
                'title' => "Need Help? We have Got Answers",
                'description' => 'Have questions about joining eDemand or providing services? Our FAQ section offers clear and concise answers to the most common inquiries.'
            ]),
            'feature_section' => json_encode([
                'status' => 1,
                'features' => [
                    [
                        'short_headline' => 'SET YOUR OWN HOURS, SERVE YOUR WAY',
                        'title' => 'Take Control of Your Time and Business',
                        'description' => 'Enjoy unparalleled flexibility as you build your service empire. Our platform empowers you to set your own schedule, choose your clients, and balance your work life seamlessly.',
                        'position' => 'left',
                        'image' => '1742990476_7c946e2beefbe218e5b7.png'
                    ],
                    [
                        'short_headline' => 'CONNECT, CHAT, CARE',
                        'title' => 'Instant Messaging for Better Service',
                        'description' => 'Enjoy unparalleled flexibility as you build your service empire. Our platform empowers you to set your own schedule, choose your clients, and balance your work life seamlessly.',
                        'position' => 'right',
                        'image' => '1742990476_21fde946dc6b5480c1dc.png'
                    ],
                    [
                        'short_headline' => 'YOUR SERVICE, YOUR RULES',
                        'title' => 'Take Charge of Your Service Business',
                        'description' => "Create detailed service listings including a unique name, categorize your service for easy discovery, and outline the specific tasks involved. Enhance your listing with relevant files like images or documents. Provide a clear and informative description of your service, including pricing details and frequently asked questions.\r\n\r\nManage your service status, cancellation policy, and payment options. Choose whether your service is offered at your location or at the customers doorstep.",
                        'position' => 'left',
                        'image' => '1742990476_69bd7d61c27daa6346ce.png'
                    ]
                ]
            ])
        ];
        // Step 2: Convert full setting into JSON
        $jsonValue = json_encode($setting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Step 3: Prepare raw SQL insert query
        $sql = "INSERT INTO `settings` (`id`, `variable`, `value`, `created_at`, `updated_at`)
                VALUES (NULL, 'become_provider_page_settings', " . $db->escape($jsonValue) . ", NOW(), NULL)";
        // Step 4: Execute query

        echo $sql;
    }
}
