<?php
/*
=======================
Handyman APIs
=======================
*/

$routes->group('partner/api/v1', ['filter' => ['language', 'handyman_api']], function ($routes) {
    /** BOOKINGS API ROUTES - START */
    $routes->post('get_bookings', 'Apis\Handyman\BookingsApiController::get_bookings');
    $routes->post('get_booking_details', 'Apis\Handyman\BookingsApiController::get_booking_details');
    $routes->post('update_booking_status', 'Apis\Handyman\BookingsApiController::update_status');
    $routes->post('update_handyman_location', 'Apis\Handyman\BookingsApiController::update_location');
    /** BOOKINGS API ROUTES - END */

    /** PROFILE API ROUTES - START */
    $routes->post('get_handyman_profile', 'Apis\Handyman\ProfileApiController::get_profile');
    $routes->post('update_handyman_profile', 'Apis\Handyman\ProfileApiController::update_profile');
    // $routes->post('update_handyman_availability', 'Apis\Handyman\ProfileApiController::update_availability');
    /** PROFILE API ROUTES - END */

    /** REVIEWS API ROUTES - START */
    $routes->post('get_my_reviews', 'Apis\Handyman\ReviewsApiController::get_my_reviews');
    /** REVIEWS API ROUTES - END */

    /** CASH COLLECTION API ROUTES - START */
    $routes->post('get_cash_collection_list', 'Apis\Handyman\CashCollectionApiController::get_list');
    /** CASH COLLECTION API ROUTES - END */

    /** DASHBOARD API ROUTES - START */
    $routes->post('get_handyman_dashboard', 'Apis\Handyman\DashboardApiController::get_dashboard');
    /** DASHBOARD API ROUTES - END */
});
