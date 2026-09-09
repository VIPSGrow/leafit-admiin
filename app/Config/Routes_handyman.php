<?php
/*
==================================
    Handyman Panel Routes
==================================
*/
$routes->get('/handyman/login', 'Auth::login');

$routes->group('', ['filter' => 'protected'], function ($routes) {

    $routes->get('profile', 'Handyman\Profile::index');
    $routes->post('handyman/profile/update', 'Handyman\Profile::update');
    $routes->post('handyman/profile/change_password', 'Handyman\Profile::change_password');

    $routes->get('handyman/', 'Handyman\Dashboard::index');
    $routes->get('handyman/dashboard', 'Handyman\Dashboard::index');
    $routes->get('handyman/bookings', 'Handyman\Bookings::index');
    $routes->get('handyman/bookings/list_data', 'Handyman\Bookings::list_data');
    $routes->get('handyman/bookings/(:num)', 'Handyman\Bookings::view/$1');
    $routes->get('handyman/bookings/status/(:num)', 'Handyman\Bookings::booking_data/$1');
    $routes->get('handyman/bookings/live_tracking/(:num)', 'Handyman\Bookings::live_tracking/$1');
    $routes->get('handyman/bookings/tracker_location/(:num)', 'Handyman\Bookings::tracker_location/$1');
    $routes->get('handyman/live_tracking', 'Handyman\Bookings::index');
    $routes->get('handyman/cash-collection', 'Handyman\CashCollection::index');
    $routes->get('handyman/cash-collection/list_data', 'Handyman\CashCollection::list_data');
    $routes->get('handyman/reviews', 'Handyman\Reviews::index');
    $routes->get('handyman/reviews/list_data', 'Handyman\Reviews::list_data');
    $routes->post('handyman/bookings/update_status', 'Handyman\Bookings::update_status');
    $routes->get('handyman/chat', 'Handyman\Chat::index');
    $routes->post('handyman/chat/get_customer_list', 'Handyman\Chat::get_customer_list');
    $routes->post('handyman/chat/booking_chat_list', 'Handyman\Chat::booking_chat_list');
    $routes->post('handyman/chat/store_chat', 'Handyman\Chat::store_chat');
    $routes->post('handyman/chat/mark_as_read', 'Handyman\Chat::mark_as_read');
    $routes->post('handyman/chat/save_web_token', 'Handyman\Chat::save_web_token');
    $routes->post('handyman/chat/sidebar_unread_users_count', 'Handyman\Chat::sidebar_unread_users_count');

    $routes->get('handyman/notifications', 'Handyman\Notifications::index');
    $routes->get('handyman/notifications/recent', 'Handyman\Notifications::recent');
    $routes->get('handyman/notifications/table', 'Handyman\Notifications::table');
    $routes->post('handyman/notifications/mark_all_read', 'Handyman\Notifications::mark_all_read');
    $routes->post('handyman/notifications/mark_read', 'Handyman\Notifications::mark_read');
    $routes->post('handyman/notifications/validate_redirect_target', 'Handyman\Notifications::validate_redirect_target');
});
