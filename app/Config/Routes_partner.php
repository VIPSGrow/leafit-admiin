<?php
/*
==================================
        Parnter Panel Routes
==================================
*/
// Login
$routes->get('/partner/login', 'Auth::login');
//check number
$routes->get('auth/check_number', 'Auth::check_number');
$routes->post('auth/check_number', 'Auth::check_number');
$routes->get('auth/check_identity_for_forgot_password', 'Auth::check_identity_for_forgot_password');
$routes->post('auth/check_identity_for_forgot_password', 'Auth::check_identity_for_forgot_password');
$routes->get('auth/reset_password_otp', 'Auth::reset_password_otp');
$routes->post('auth/reset_password_otp', 'Auth::reset_password_otp');
$routes->get('auth/send_sms_otp', 'Auth::send_sms_otp');
$routes->post('auth/send_sms_otp', 'Auth::send_sms_otp');
$routes->get('auth/verify_sms_otp', 'Auth::verify_sms_otp');
$routes->post('auth/verify_sms_otp', 'Auth::verify_sms_otp');
$routes->get('auth/verify_email_otp', 'Auth::verify_email_otp');
$routes->post('auth/verify_email_otp', 'Auth::verify_email_otp');



$routes->group('', ['filter' => 'protected'], function ($routes) {

        // Provider panel maintenance view: when maintenance mode is active, only this page is allowed.
        $routes->get('partner/maintenance', 'Partner\Partner::maintenance');

        // Dashbord
        $routes->get('partner/', 'Partner\Dashboard::index');
        $routes->get('/partner/dashboard', 'Partner\Dashboard::index');
        $routes->get('/partner/dashboard/fetch_sales', 'Partner\Dashboard::fetch_sales');
        $routes->get('/partner/dashboard/fetch_data', 'Partner\Dashboard::fetch_data');
        $routes->get('/partner/stripe', 'Partner\StripePaymentController::index');
        // Services For Partners 
        $routes->add('partner/services', 'Partner\Services::index');
        $routes->add('partner/services/list', 'Partner\Services::list');
        $routes->add('partner/services/view/(:any)', 'Partner\Services::view_service');
        $routes->add('partner/services/add', 'Partner\Services::add');
        $routes->add('partner/services/add_service', 'Partner\Services::add_service');
        $routes->add('partner/services/update_service', 'Partner\Services::update_service');
        $routes->add('partner/services/delete_service', 'Partner\Services::delete');
        $routes->add('partner/services/edit_service/(:any)', 'Partner\Services::edit_service');
        $routes->add('partner/services/duplicate/(:any)', 'Partner\Services::duplicate');
        $routes->add('partner/services/bulk_import_services/', 'Partner\ServiceBulkController::bulk_import_services');
        $routes->add('partner/services/bulk_import_service_upload/', 'Partner\ServiceBulkController::bulk_import_service_upload');
        $routes->add('partner/services/download-sample-for-insert/', 'Partner\ServiceBulkController::downloadSampleForInsert');
        $routes->add('partner/services/download-sample-for-update/', 'Partner\ServiceBulkController::downloadSampleForUpdate');
        $routes->add('partner/services/Service-Add-Instructions/', 'Partner\ServiceBulkController::ServiceAddInstructions');
        $routes->add('partner/services/Service-Update-Instructions/', 'Partner\ServiceBulkController::ServiceUpdateInstructions');
        $routes->add('partner/services/remove_seo_image', 'Partner\Services::remove_seo_image');

        // for profile
        $routes->add('partner/profile', 'Partner\Profile::index');
        $routes->add('partner/update-profile', 'Partner\Profile::update');
        $routes->add('partner/update_profile', 'Partner\Profile::update_profile');
        // SEO Settings (separate module)
        $routes->add('partner/seo', 'Partner\SeoController::index');
        $routes->add('partner/seo/update', 'Partner\SeoController::update');
        $routes->add('partner/seo/remove_seo_image', 'Partner\SeoController::remove_seo_image');
        // Slot Management module — Working Hours (moved out of profile)
        $routes->add('partner/working-hours', 'Partner\WorkingHoursController::index');
        $routes->add('partner/working-hours/update', 'Partner\WorkingHoursController::update');
        // Slot Management module — Slot Configuration (interval, capacity, booking window, buffers)
        $routes->add('partner/slot-configuration', 'Partner\SlotConfigurationController::index');
        $routes->add('partner/slot-configuration/update', 'Partner\SlotConfigurationController::update');
        // Slot Management module — Leaves
        $routes->add('partner/leaves', 'Partner\LeavesController::index');
        $routes->add('partner/leaves/list', 'Partner\LeavesController::leaves_list');
        $routes->add('partner/leaves/add', 'Partner\LeavesController::add');
        $routes->add('partner/leaves/delete', 'Partner\LeavesController::delete');
        // KYC for Partner
        $routes->add('partner/kyc', 'Partner\Kyc::index');
        // Categories
        $routes->add('partner/categories', 'Partner\Categories::index');
        $routes->add('partner/categories/list', 'Partner\Categories::list');


        // orders
        $routes->add('partner/orders', 'Partner\Orders::index');
        $routes->add('partner/orders/list', 'Partner\Orders::list');
        $routes->add('partner/orders/veiw_orders/(:any)', 'Partner\Orders::view_orders');
        $routes->add('partner/orders/invoice/(:any)', 'Partner\Orders::invoice');
        $routes->add('partner/orders/invoice_table/(:any)', 'Partner\Orders::invoice_table');
        $routes->add('partner/orders/order_summary_table/(:any)', 'Partner\Orders::order_summary_table');
        $routes->add('partner/orders/update_order_status', 'Partner\Orders::update_order_status');
        $routes->add('partner/orders/get_slots', 'Partner\Orders::get_slots');
        $routes->post('partner/orders/assign_handyman', 'Partner\Orders::assign_handyman');
        $routes->post('partner/orders/unassign_handyman', 'Partner\Orders::unassign_handyman');
        $routes->post('partner/orders/set_lead_handyman', 'Partner\Orders::set_lead_handyman');
        $routes->add('partner/orders/change_order_status', 'Partner\Orders::change_order_status');
        $routes->add('partner/orders/newList', 'Partner\Orders::newList');
        $routes->add('partner/orders/test', 'Partner\Orders::test');
        $routes->get('partner/orders/live_tracking/(:num)', 'Partner\Orders::live_tracking/$1');
        $routes->get('partner/orders/tracker_location/(:num)', 'Partner\Orders::tracker_location/$1');
        $routes->post('partner/orders/update_partner_location', 'Partner\Orders::update_partner_location');
        $routes->get('partner/orders/live_map', 'Partner\Orders::live_map');
        $routes->get('partner/orders/live_map_data', 'Partner\Orders::live_map_data');
        $routes->get('partner/orders/orders_calendar', 'Partner\Orders::orders_calender');
        // promot codes
        $routes->add('partner/promo_codes', 'Partner\Promo_codes::index');
        $routes->add('partner/promo_codes/add', 'Partner\Promo_codes::add');
        $routes->add('partner/promo_codes/save', 'Partner\Promo_codes::save');
        $routes->add('partner/promo_codes/list', 'Partner\Promo_codes::list');
        $routes->add('partner/promo_codes/delete', 'Partner\Promo_codes::delete');
        $routes->add('partner/promo_codes/get_promocode_data', 'Partner\Promo_codes::get_promocode_data');

        $routes->add('partner/promo_codes/duplicate/(:any)', 'Partner\Promo_codes::duplicate');



        $routes->add('partner/withdrawal_requests', 'Partner\Withdrawal_requests::index');
        $routes->add('partner/withdrawal_requests/save', 'Partner\Withdrawal_requests::save');
        $routes->add('partner/withdrawal_requests/send', 'Partner\Withdrawal_requests::send');
        $routes->add('partner/withdrawal_requests/delete', 'Partner\Withdrawal_requests::delete');
        $routes->add('partner/withdrawal_requests/list', 'Partner\Withdrawal_requests::list');
        $routes->add('partner/review', 'Partner\Partner::review');
        $routes->add('partner/review_list', 'Partner\Partner::review_list');
        $routes->add('partner/cash_collection', 'Partner\Partner::cash_collection');
        $routes->add('partner/cash_collection_list', 'Partner\Partner::cash_collection_history_list');
        $routes->add('partner/handyman-cash-collection', 'Partner\HandymanCashCollection::index');
        $routes->add('partner/handyman-cash-collection/list_data', 'Partner\HandymanCashCollection::list_data');
        $routes->add('partner/handyman-cash-collection/collect', 'Partner\HandymanCashCollection::collect');
        $routes->add('partner/settlement', 'Partner\Partner::settlement');
        $routes->add('partner/settlement_list', 'Partner\Partner::settlement_list');
        $routes->add('partner/transactions', 'Partner\Transactions::index');
        $routes->add('partner/transactions/list', 'Partner\Transactions::list');
        $routes->add('partner/update_partner', 'Admin\ProviderController::update_partner');
        $routes->add('partner/subscription', 'Partner\Partner::subscription_list');
        $routes->add('partner/subscription_history', 'Partner\Partner::subscription_history');
        $routes->add('partner/subscription-payment', 'Partner\Subscription::subscription_payment');
        $routes->add('partner/subscription_history_list', 'Partner\Partner::subscription_history_list');
        $routes->add('partner/subscription/pre-payment-setup', 'Partner\Subscription::pre_payment_setup');
        $routes->post('partner/make_payment_for_subscription', 'Partner\Partner::make_payment_for_subscription');
        $routes->get('partner/cashfree_checkout', 'Partner\Partner::cashfree_checkout');
        $routes->get('partner/cashfree_return', 'Partner\Partner::cashfree_return');
        $routes->get('partner/paystack_return', 'Partner\Partner::paystack_return');
        $routes->get('partner/paypal_return', 'Partner\Partner::paypal_return');
        $routes->get('partner/stripe_success', 'Partner\Partner::success');
        $routes->get('partner/cancel', 'Partner\Partner::cancel');

        $routes->get('partner/flutterwave_callback', 'Partner\Partner::flutterwave_callback');
        $routes->get('partner/xendit_subscription_success', 'Partner\Partner::xendit_subscription_success');


        $routes->get('partner/payment/checkout/(:any)', 'Partner\Partner::checkout');
        $routes->get('payment/intent/(:any)', 'Partner\Partner::createPaymentIntent/');
        $routes->get('razorpay-payment-form', 'Partner\Partner::payWithRazorpay');
        $routes->post('razorpay-payment', 'Partner\Partner::processPayment');
        $routes->add('partner/settlement_cashcollection_history', 'Partner\Partner::settlement_cashcollection_history');
        $routes->add('partner/settlement_cashcollection_history_list', 'Partner\Partner::settlement_cashcollection_history_list');
        //routes for chat
        $routes->add('partner/admin-support', 'Partner\Chats::admin_support_index');
        $routes->add('partner/provider-chats', 'Partner\Chats::provider_chats_index');

        $routes->add('partner/provider-booking-chats/(:any)', 'Partner\Chats::provider_chats_index/$1');

        $routes->add('partner/store_admin_chat', 'Partner\Chats::store_admin_chat');
        $routes->add('partner/store_booking_chat', 'Partner\Chats::store_booking_chat');
        $routes->add('partner/chat_get_all_messages', 'Partner\Chats::getAllMessage');
        $routes->add('partner/save_web_token', 'Partner\Partner::save_web_token');
        $routes->add('partner/provider_booking_chat_list', 'Partner\Chats::provider_booking_chat_list');
        $routes->add('partner/check_booking_status', 'Partner\Chats::check_booking_status');
        $routes->add('partner/get_lead_handyman', 'Partner\Chats::get_lead_handyman');

        $routes->add('partner/get_customer', 'Partner\Chats::get_customer');
        $routes->add('partner/mark_chat_as_read', 'Partner\Chats::mark_chat_as_read');
        $routes->get('partner/sidebar_unread_users_count', 'Partner\Chats::sidebar_unread_users_count');

        // Report & Block routes
        $routes->get('partner/get_report_reasons', 'Partner\ReportController::get_report_reasons');
        $routes->post('partner/submit_report', 'Partner\ReportController::submit_report');
        // $routes->post('partner/unblock_user', 'Partner\ReportController::unblock_user');

        /**
         * Partner Notifications (web panel)
         *
         * Why:
         * - The partner header notification dropdown needs a lightweight endpoint
         *   to fetch the latest 5 notifications.
         * - Partners also need a "View all notifications" page.
         *
         * Security:
         * - These routes live in the protected group so only authenticated partners can access them.
         */
        $routes->get('partner/notifications', 'Partner\Notifications::index');
        $routes->get('partner/notifications/recent', 'Partner\Notifications::recent');
        $routes->get('partner/notifications/list', 'Partner\Notifications::list');
        $routes->get('partner/notifications/table', 'Partner\Notifications::table');
        $routes->post('partner/notifications/mark_all_read', 'Partner\Notifications::mark_all_read');
        // Mark a single notification as read (used on click redirect).
        $routes->post('partner/notifications/mark_read', 'Partner\Notifications::mark_read');
        $routes->post('partner/notifications/validate_redirect_target', 'Partner\Notifications::validate_redirect_target');

        $routes->add('partner/gallery-view', 'Partner\Gallery::index');
        $routes->add('partner/gallery/get-gallery-files/(:any)', 'Partner\Gallery::GetGallaryFiles');
        $routes->add('partner/gallery/download-all', 'Partner\Gallery::downloadAll');
        $routes->add('partner/JobRequests/', 'Partner\JobRequests::index');
        $routes->add('partner/manage_category_preference', 'Partner\JobRequests::manage_category_preference');
        $routes->add('partner/make_bid', 'Partner\JobRequests::make_bid');
        $routes->add('partner/manage_accepting_custom_jobs', 'Partner\JobRequests::manage_accepting_custom_jobs');

        $routes->add('partner/check-block-status', 'Partner\Chats::check_block_status');
        $routes->add('partner/delete-chat', 'Partner\Chats::delete_chat');
        $routes->add('partner/unblock-user', 'Partner\Chats::unblock_user');

        //Handyman Management Routes
        $routes->group('partner/handymen', function ($routes) {
                $routes->get('/', 'Partner\Handyman::index');
                $routes->get('list', 'Partner\Handyman::list');
                $routes->post('store', 'Partner\Handyman::store');
                $routes->post('update', 'Partner\Handyman::update');
                $routes->post('delete', 'Partner\Handyman::delete');
                $routes->post('active-bookings', 'Partner\Handyman::activeBookings');
                $routes->post('toggle-status', 'Partner\Handyman::toggleStatus');
                $routes->post('toggle-availability', 'Partner\Handyman::toggleAvailability');
                $routes->get('view/(:num)', 'Partner\Handyman::view/$1');
                $routes->get('overview/(:num)', 'Partner\Handyman::overview/$1');
                $routes->get('tracker-location/(:num)', 'Partner\Handyman::trackerLocation/$1');
                $routes->get('bookings-list/(:num)', 'Partner\Handyman::bookingsList/$1');
                $routes->get('cash-collection-list/(:num)', 'Partner\Handyman::cashCollectionList/$1');
                $routes->get('reviews-list/(:num)', 'Partner\Handyman::reviewsList/$1');
        });
});
