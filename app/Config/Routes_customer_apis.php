<?php
/*
=======================
Customer APIs
=======================
*/

// $routes->post('api/v1/index', 'Apis\Customer\V1::index');
$routes->group('api/v1', ['filter' => 'language'], function ($routes) {
    /** PARTNER AUTH APIS - START (Used in become provider page registration)*/
    $routes->post('register_provider', 'Apis\Provider\AuthApiController::register');
    $routes->post('verify_provider', 'Apis\Provider\AuthApiController::verify_user');
    $routes->post('verify_provider_otp', 'Apis\Provider\AuthApiController::verify_otp');
    $routes->post('resend_provider_otp', 'Apis\Provider\AuthApiController::resend_otp');
    /** PARTNER AUTH APIS - END */

    /** USER AUTH APIs - START */
    $routes->post('manage_user', 'Apis\Customer\UserAuthApiController::manage_user');
    $routes->post('update_user', 'Apis\Customer\UserAuthApiController::update_user');
    $routes->post('update_fcm', 'Apis\Customer\UserAuthApiController::update_fcm');
    $routes->post('verify_user', 'Apis\Customer\UserAuthApiController::verify_user');
    $routes->post('delete_user_account', 'Apis\Customer\UserAuthApiController::delete_user_account');
    $routes->post('logout', 'Apis\Customer\UserAuthApiController::logout');
    $routes->post('get_user_info', 'Apis\Customer\UserAuthApiController::get_user_info');
    $routes->post('verify_otp', 'Apis\Customer\UserAuthApiController::verify_otp');
    $routes->post('resend_otp', 'Apis\Customer\UserAuthApiController::resend_otp');
    $routes->post('change_password', 'Apis\Customer\UserAuthApiController::change_password');
    /** USER AUTH APIs - END */

    /** SEO SETTINGS APIs - START */
    $routes->post('get_seo_settings', 'Apis\Customer\SeoSettingsApiController::get_seo_settings');
    $routes->get('get_site_map_data', 'Apis\Customer\SeoSettingsApiController::get_site_map_data');
    /** SEO SETTINGS APIs - END */

    /** LANGUAGE APIs - START (unified controller serves both Customer and Provider) */
    $routes->get('get_language_list', 'Apis\LanguageApiController::get_language_list');
    $routes->post('get_language_json_data', 'Apis\LanguageApiController::get_language_json_data');
    /** LANGUAGE APIs - END */

    /** CATEGORY APIs - START */
    $routes->post('get_categories', 'Apis\Customer\CategoryApiController::get_categories');
    $routes->post('get_sub_categories', 'Apis\Customer\CategoryApiController::get_sub_categories');
    $routes->post('get_parent_categories', 'Apis\Customer\CategoryApiController::get_parent_categories');
    $routes->post('get_all_categories', 'Apis\Customer\CategoryApiController::get_all_categories');
    $routes->post('get_categories_hierarchical', 'Apis\Customer\CategoryApiController::get_categories_hierarchical');
    $routes->post('get_parent_category_slug', 'Apis\Customer\CategoryApiController::get_parent_category_slug');
    /** CATEGORY APIs - END */

    /** BLOG APIs - START */
    $routes->get('get_blogs', 'Apis\Customer\BlogApiController::get_blogs');
    $routes->post('get_blog_details', 'Apis\Customer\BlogApiController::get_blog_details');
    $routes->get('get_blog_categories', 'Apis\Customer\BlogApiController::get_blog_categories');
    $routes->get('get_blog_tags', 'Apis\Customer\BlogApiController::get_blog_tags');
    /** BLOG APIs - END */

    /** NOTIFICATION APIs - START */
    $routes->post('manage_notification', 'Apis\Customer\NotificationApiController::manage_notification');
    $routes->post('get_notifications', 'Apis\Customer\NotificationApiController::get_notifications');
    /** NOTIFICATION APIs - END */

    /** SETTINGS APIs - START */
    $routes->get('get_settings', 'Apis\Customer\SettingsApiController::get_settings');
    $routes->post('get_settings', 'Apis\Customer\SettingsApiController::get_settings');
    $routes->post('get_web_landing_page_settings', 'Apis\Customer\SettingsApiController::get_web_landing_page_settings');
    $routes->post('get_become_provider_settings', 'Apis\Customer\SettingsApiController::get_become_provider_settings');
    $routes->post('get_page_setting', 'Apis\PageSettingsApiController::get_page_setting');
    $routes->post('get_custom_pages', 'Apis\PageSettingsApiController::get_custom_pages');
    $routes->post('get_chat_questions', 'Apis\ChatQuestionsApiController::get_chat_questions');
    /** SETTINGS APIs - END */

    /** ADDRESS APIs - START */
    $routes->post('add_address', 'Apis\Customer\AddressApiController::add_address');
    $routes->post('get_address', 'Apis\Customer\AddressApiController::get_address');
    $routes->post('delete_address', 'Apis\Customer\AddressApiController::delete_address');
    /** ADDRESS APIs - END */

    /** CUSTOM FIELDS APIs - START */
    $routes->post('get_address_custom_fields', 'Apis\Customer\CustomFieldsApiController::get_address_custom_fields');
    /** CUSTOM FIELDS APIs - END */

    /** CART APIs - START */
    $routes->post('manage_cart', 'Apis\Customer\CartApiController::manage_cart');
    $routes->post('get_cart', 'Apis\Customer\CartApiController::get_cart');
    $routes->post('remove_from_cart', 'Apis\Customer\CartApiController::remove_from_cart');
    /** CART APIs - END */

    /** RATINGS APIs - START */
    $routes->post('get_ratings', 'Apis\Customer\RatingsApiController::get_ratings');
    $routes->post('add_rating', 'Apis\Customer\RatingsApiController::add_rating');
    $routes->post('update_rating', 'Apis\Customer\RatingsApiController::update_rating');
    /** RATINGS APIs - END */

    /** HANDYMAN REVIEW APIs - START */
    $routes->post('save_handyman_review', 'Apis\Customer\HandymanReviewApiController::save_handyman_review');
    $routes->post('delete_handyman_review', 'Apis\Customer\HandymanReviewApiController::delete_handyman_review');
    /** HANDYMAN REVIEW APIs - END */

    /** PLACES APIs - START */
    $routes->get('get_places_for_app', 'Apis\PlacesApiController::get_places');
    $routes->get('get_place_details_for_app', 'Apis\PlacesApiController::get_place_details');
    $routes->get('get_places_for_web', 'Apis\PlacesApiController::get_places');
    $routes->get('get_place_details_for_web', 'Apis\PlacesApiController::get_place_details');
    /** PLACES APIs - END */
    
    /** CUSTOM JOB REQUESTS APIs - START */
    $routes->post('make_custom_job_request', 'Apis\Customer\CustomJobRequestsApiController::make_custom_job_request');
    $routes->post('fetch_my_custom_job_requests', 'Apis\Customer\CustomJobRequestsApiController::fetch_my_custom_job_requests');
    $routes->post('fetch_custom_job_bidders', 'Apis\Customer\CustomJobRequestsApiController::fetch_custom_job_bidders');
    $routes->post('cancle_custom_job_request', 'Apis\Customer\CustomJobRequestsApiController::cancle_custom_job_request');
    /** CUSTOM JOB REQUESTS APIs - END */

    /** FAQS APIs - START */
    $routes->post('get_faqs', 'Apis\Customer\FaqsApiController::get_faqs');
    /** FAQS APIs - END */
    
    /** CHAT APIs - START */
    $routes->post('send_chat_message', 'Apis\Customer\ChatApiController::send_chat_message');
    $routes->post('get_chat_history', 'Apis\Customer\ChatApiController::get_chat_history');
    $routes->post('mark_message_as_read', 'Apis\Customer\ChatApiController::mark_message_as_read');
    $routes->post('get_chat_providers_list', 'Apis\Customer\ChatApiController::get_chat_providers_list');
    $routes->post('delete_chat_user', 'Apis\Customer\ChatApiController::delete_chat_user');
    /** CHAT APIs - END */

    /** REPORT AND BLOCK APIs - START */
    $routes->get('get_report_reasons', 'Apis\Customer\ReportAndBlockApiController::get_report_reasons');
    $routes->post('block_user', 'Apis\Customer\ReportAndBlockApiController::block_user');
    $routes->post('unblock_user', 'Apis\Customer\ReportAndBlockApiController::unblock_user');
    $routes->get('get_blocked_providers', 'Apis\Customer\ReportAndBlockApiController::get_blocked_providers');
    /** REPORT AND BLOCK APIs - END */

    /** HOME SCREEN APIs - START */
    $routes->post('get_home_screen_data', 'Apis\Customer\HomeScreenApiController::get_home_screen_data');
    /** HOME SCREEN APIs - END */

    /** TRANSACTION APIs - START */
    $routes->post('add_transaction', 'Apis\Customer\TransactionApiController::add_transaction');
    $routes->post('get_transactions', 'Apis\Customer\TransactionApiController::get_transactions');
    /** TRANSACTION APIs - END */

    /** PROMO CODES APIs - START */
    $routes->post('validate_promo_code', 'Apis\Customer\PromoCodesApiController::validate_promo_code');
    $routes->post('get_promo_codes', 'Apis\Customer\PromoCodesApiController::get_promo_codes');
    /** PROMO CODES APIs - END */

    /** CONTACT US APIs - START */
    $routes->post('contact_us_api', 'Apis\Customer\ContactUsApiController::contact_us_api');
    /** CONTACT US APIs - END */

    /** SLIDERS APIs - START */
    $routes->post('get_sliders', 'Apis\Customer\SlidersApiController::get_sliders');
    /** SLIDERS APIs - END */

    /** COUNTRY CODES APIs - START */
    $routes->get('get_country_codes', 'Apis\CountryCodesApiController::get_country_codes');
    /** COUNTRY CODES APIs - END */

    /** SERVICES APIs - START */
    $routes->post('get_services', 'Apis\Customer\ServicesApiController::get_services');
    /** SERVICES APIs - END */

    /** PROVIDERS APIs - START */
    $routes->post('get_providers', 'Apis\Customer\ProvidersApiController::get_providers');
    $routes->post('get_providers_on_map', 'Apis\Customer\ProvidersApiController::get_providers_on_map');
    /** PROVIDERS APIs - END */

    /** SEARCH PROVIDER SERVICES APIs - START */
    $routes->post('search_services_providers', 'Apis\Customer\SearchProviderServiceApiController::search_services_providers');
    /** SEARCH PROVIDER SERVICES APIs - END */

    /** BOOKMARK APIs - START */
    $routes->post('book_mark', 'Apis\Customer\BookmarkApiController::bookmark');
    /** BOOKMARK APIs - END */

    /** PROVIDER AVAILABILITY APIs - START */
    $routes->post('get_available_slots', 'Apis\Customer\ProviderAvailabilityApiController::get_available_slots');
    $routes->post('check_available_slot', 'Apis\Customer\ProviderAvailabilityApiController::check_available_slot');
    $routes->post('release_slot_lock', 'Apis\Customer\ProviderAvailabilityApiController::release_slot_lock');
    $routes->post('provider_check_availability', 'Apis\Customer\ProviderAvailabilityApiController::provider_check_availability');
    /** PROVIDER AVAILABILITY APIs - END */


    $routes->post('place_order', 'Apis\Customer\OrdersApiController::place_order');
    $routes->post('get_orders', 'Apis\Customer\OrdersApiController::get_orders');
    $routes->post('get_live_tracking_data', 'Apis\Customer\OrdersApiController::get_live_tracking_data');
    $routes->post('update_order_status', 'Apis\Customer\OrdersApiController::update_order_status');
    $routes->post('razorpay_create_order', 'Apis\Customer\OrdersApiController::razorpay_create_order');
    $routes->post('cashfree_create_order', 'Apis\Customer\OrdersApiController::cashfree_create_order');
    $routes->post('get_cashfree_order_status', 'Apis\Customer\OrdersApiController::get_cashfree_order_status');
    $routes->post('create_stripe_payment_intent', 'Apis\Customer\OrdersApiController::create_stripe_payment_intent');
    $routes->post('get_stripe_payment_status', 'Apis\Customer\OrdersApiController::get_stripe_payment_status');
    $routes->post('manage_service', 'Apis\Customer\OrdersApiController::manage_service');
    $routes->post('get_paypal_link', 'Apis\Customer\OrdersApiController::get_paypal_link');
    $routes->get('paypal_return', 'Apis\Customer\OrdersApiController::paypal_return');
    $routes->get('app_payment_status', 'Apis\Customer\OrdersApiController::app_payment_status');
    $routes->post('invoice-download', 'Apis\Customer\OrdersApiController::invoice_download');
    $routes->post('verify-transaction', 'Apis\Customer\OrdersApiController::verify_transaction');
    $routes->get('capturePayment', 'Apis\Customer\OrdersApiController::capturePayment');
    $routes->get('paystack_transaction_webview', 'Apis\Customer\OrdersApiController::paystack_transaction_webview');
    $routes->get('app_paystack_payment_status', 'Apis\Customer\OrdersApiController::app_paystack_payment_status');
    $routes->get('flutterwave_webview', 'Apis\Customer\OrdersApiController::flutterwave_webview');
    $routes->get('flutterwave_payment_status', 'Apis\Customer\OrdersApiController::flutterwave_payment_status');
    $routes->get('xendit_payment_status', 'Apis\Customer\OrdersApiController::xendit_payment_status');

    /** REASON API ROUTES - START */
    $routes->post('get_reasons', 'Apis\ReasonApiController::get_reasons');
    /** REASON API ROUTES - END */
});