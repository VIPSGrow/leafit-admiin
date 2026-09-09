<?php

$routes->get('/admin/login', 'Auth::login');
$routes->get('/', 'Admin\Dashboard::index');

$routes->add('unsubscribe_link/(:any)', 'Admin\SendEmail::unsubscribe_link_view');
$routes->add('admin/unsubscribe_email_op', 'Admin\SendEmail::unsubscription_email_operation');

$routes->add('admin/settings/legal-pages/preview/(:segment)', 'Admin\PolicyController::legal_page_preview/$1');
$routes->add('admin/settings/about-us-preview', 'Admin\PolicyController::about_us_page_preview');
$routes->add('admin/settings/contact-us-preview', 'Admin\PolicyController::contact_us_page_preview');



$routes->group('', ['filter' => 'protected'], function ($routes) {

    //Admin login
    $routes->get('payment-form', 'RazorpayController::payWithRazorpay');
    $routes->post('payment', 'RazorpayController::processPayment');
    $routes->get('update_subscription_status', 'Admin\Dashboard::update_subscription_status');
    $routes->get('cancle_elapsed_time_order', 'Admin\Dashboard::cancle_elapsed_time_order');

    $routes->get('/provider-details/(:any)', 'Auth::providerDetails');


    // Route::get('/product-details/{slug}', [SettingController::class, 'webPageURL'])->name('deep-link');
    $routes->add('admin/forgot-password', 'Admin\Dashboard::forgot_password');
    //...

    $routes->add('admin/dashboard', 'Admin\Dashboard::index');
    $routes->add('admin/dashboard/recent_booking', 'Admin\Dashboard::recent_orders');
    $routes->add('admin/dashboard/top_trending_services', 'Admin\Dashboard::top_trending_services');
    $routes->add('admin/profile', 'Admin\Profile::index');
    $routes->add('admin/profile/update', 'Admin\Profile::update');
    $routes->add('admin/profile/password', 'Admin\Profile::password');
    $routes->add('admin/profile/password/update', 'Admin\Profile::passwordUpdate');

    // Custom Fields
    $routes->get('admin/custom-fields', 'Admin\CustomFields::index');
    $routes->get('admin/custom-fields/list', 'Admin\CustomFields::list');
    $routes->post('admin/custom-fields/save', 'Admin\CustomFields::save');
    $routes->post('admin/custom-fields/update', 'Admin\CustomFields::update');
    $routes->post('admin/custom-fields/delete', 'Admin\CustomFields::delete');
    $routes->post('admin/custom-fields/change-order', 'Admin\CustomFields::change_order');

    //LANGUAGE ROUTES

    // $routes->get('lang/(:alpha)', 'Language::switchLang/$1');
    // $routes->get('lang/(:segment)', 'Language::index/$1');
    // $routes->get('lang/(:any)', 'Language::index\$1');
    $routes->get('lang/(:any)', 'Language::index/$1');

    $routes->post('lang/updateIsRtl', 'Language::updateIsRtl');
    $routes->get('admin/languages/', "Admin\Languages::index");
    // $routes->post('admin/languages/create', "Admin\Languages::create");
    // $routes->post('admin/languages/set_labels', "Admin\Languages::set_labels");
    $routes->get('admin/languages/change/(:any)', "Admin\Languages::change/$1");
    $routes->add('admin/language/remove', 'Admin\Languages::remove');
    $routes->add('admin/language/get_dropdown_data', 'Admin\Languages::get_languages_for_dropdown');
    $routes->add('admin/upload_update_file', 'Admin\Updater::upload_update_file');
    $routes->post('admin/languages/insert', "Admin\Languages::insert");
    $routes->get('download_sample_file/(:any)', 'Admin\Languages::language_sample');
    $routes->add('download_old_file/(:any)/(:any)', 'Admin\Languages::language_old');
    $routes->add('admin/language/list', 'Admin\Languages::list');
    $routes->add('admin/language/update', 'Admin\Languages::update');
    // $routes->add('admin/language/remove_langauge', 'Admin\Languages::remove');
    $routes->add('admin/language/store_default_language', 'Admin\Languages::store_default_language');
    // $routes->add('admin/language/upload_image', 'Admin\Languages::upload_image');

    //SETTINGS ROUTES
    $routes->add('admin/settings', 'Admin\Settings::index');
    // $routes->add('admin/settings/themes', 'Admin\Settings::themes');
    $routes->add('admin/settings/general-settings', 'Admin\GeneralSettingsController::general_settings');
    $routes->get('admin/settings/get-approved-providers', 'Admin\GeneralSettingsController::get_approved_providers');
    $routes->post('admin/settings/save-provider-distances', 'Admin\GeneralSettingsController::save_provider_distances');
    $routes->get('admin/settings/chat-settings', 'Admin\ChatSettings::index');
    $routes->post('admin/settings/chat-settings/save-settings', 'Admin\ChatSettings::save_settings');
    $routes->post('admin/settings/chat-settings/add-question', 'Admin\ChatSettings::add_question');
    $routes->post('admin/settings/chat-settings/update-question', 'Admin\ChatSettings::update_question');
    $routes->post('admin/settings/chat-settings/delete-question', 'Admin\ChatSettings::delete_question');
    $routes->get('admin/settings/chat-settings/list-questions', 'Admin\ChatSettings::list_questions');
    $routes->post('admin/settings/chat-settings/reorder-questions', 'Admin\ChatSettings::reorder_questions');
    $routes->add('admin/settings/email-settings', 'Admin\EmailSettingsController::email_settings');
    $routes->add('admin/settings/pg-settings', 'Admin\PaymentGatewaySettingsController::pg_settings');
    $routes->add('admin/settings/api_key_settings', 'Admin\ApiKeySettingsController::api_key_settings');
    $routes->add('admin/settings/system_tax_settings', 'Admin\Tax::system_tax_settings');
    $routes->add('admin/settings/app_settings', 'Admin\AppSettingsController::app_settings');

    $routes->add('admin/settings/firebase_settings', 'Admin\FirebaseSettingsController::firebase_settings');

    $routes->add('admin/settings/system-settings', 'Admin\GeneralSettingsController::main_system_setting_page');
    $routes->add('admin/settings/about-us', 'Admin\PolicyController::about_us');

    $routes->add('admin/settings/app', 'Admin\AppSettingsController::app_settings');
    $routes->add('admin/settings/country_codes', 'Admin\CountryCodeController::contry_codes');
    $routes->add('admin/settings/add_contry_code', 'Admin\CountryCodeController::add_contry_code');
    $routes->add('admin/settings/fetch_contry_code', 'Admin\CountryCodeController::fetch_contry_code');
    $routes->add('admin/settings/delete_contry_code', 'Admin\CountryCodeController::delete_contry_code');
    $routes->add('admin/settings/store_default_country_code', 'Admin\CountryCodeController::store_default_country_code');
    $routes->add('admin/settings/update_country_codes', 'Admin\CountryCodeController::update_country_codes');
    $routes->add('admin/settings/web_setting', 'Admin\WebSettingsController::web_setting_page');
    $routes->add('admin/settings/web_setting_update', 'Admin\WebSettingsController::web_setting_update');
    $routes->add('admin/settings/reset-theme-colors', 'Admin\WebSettingsController::reset_theme_colors');
    $routes->add('admin/settings/sms-gateways', 'Admin\SmsController::sms_gateway_setting_index');
    $routes->add('admin/settings/sms-gateway-settings', 'Admin\SmsController::sms_gateway_setting_update');
    $routes->add('admin/settings/sms-templates', 'Admin\SmsController::sms_templates');
    $routes->add('admin/settings/sms-templates-list', 'Admin\SmsController::sms_template_list');
    $routes->add('admin/settings/edit_sms_template/(:any)', 'Admin\SmsController::edit_sms_template');
    $routes->add('admin/settings/edit-sms-templates', 'Admin\SmsController::edit_sms_template_update');
    $routes->add('admin/settings/notification-settings', 'Admin\NotificationSettings::notification_settings');
    $routes->add('admin/settings/notification_setting_update', 'Admin\NotificationSettings::notification_setting_update');
    $routes->add('admin/settings/sms-email-preview/(:any)', 'Admin\EmailSettingsController::sms_email_preview');
    $routes->add('admin/settings/updater', 'Admin\Updater::index');
    $routes->add('admin/settings/web-landing-page-settings', 'Admin\WebSettingsController::web_landing_page_settings');
    $routes->add('admin/settings/web-landing-page-settings-update', 'Admin\WebSettingsController::web_setting_landing_page_update');
    $routes->add('admin/settings/review-list', 'Admin\BecomeProviderSettingsController::review_list');
    $routes->add('admin/settings/become-provider-setting', 'Admin\BecomeProviderSettingsController::become_provider_setting_page');
    $routes->add('admin/settings/become-provider-setting-update', 'Admin\BecomeProviderSettingsController::become_provider_setting_page_update');
    $routes->add('admin/settings/notification-templates', 'Admin\NotificationSettings::notificationTemplates');
    $routes->add('admin/settings/notification-templates-list', 'Admin\NotificationSettings::notificationTemplatesList');
    $routes->add('admin/settings/edit-notification-template/(:any)', 'Admin\NotificationSettings::editNotificationTemplate');
    $routes->add('admin/settings/edit-notification-template-operation', 'Admin\NotificationSettings::editNotificationTemplateOperation');
    $routes->get('admin/settings/authentication-settings', 'Admin\LoginSettings::index');
    $routes->post('admin/settings/authentication-settings', 'Admin\LoginSettings::save');

    $routes->add('admin/settings/contact-us', 'Admin\PolicyController::contact_us');
    $routes->add('admin/settings/legal-pages/(:segment)', 'Admin\PolicyController::legal_page/$1');
    $routes->get('/customer_privacy_policy', 'Auth::customer_privacy_policy');

    //SEO SETTINGS ROUTES
    $routes->add('admin/settings/seo-settings', 'Admin\SeoSettings::index');
    $routes->post('admin/settings/add-seo-settings', 'Admin\SeoSettings::add_seo_settings');
    $routes->add('admin/settings/seo-settings-list', 'Admin\SeoSettings::seo_settings_list');
    $routes->post('admin/settings/update-seo-settings', 'Admin\SeoSettings::update_seo_settings');
    $routes->post('admin/settings/delete-seo-settings', 'Admin\SeoSettings::delete_seo_settings');
    $routes->add('admin/settings/get-seo-settings', 'Admin\SeoSettings::get_seo_settings');
    $routes->add('admin/settings/get-existing-seo-pages', 'Admin\SeoSettings::get_existing_pages');

    //CATEGORY ROUTES
    $routes->add('admin/categories/', 'Admin\Categories::index');
    $routes->add('admin/category/add_category', 'Admin\Categories::add_category');
    $routes->add('admin/category/remove_category', 'Admin\Categories::remove_category');
    $routes->add('admin/category/update_category', 'Admin\Categories::update_category');
    $routes->add('admin/categories/remove_seo_image', 'Admin\Categories::remove_seo_image');
    $routes->add('admin/categories/list', 'Admin\Categories::list');
    $routes->add('admin/categories/get_all_for_select', 'Admin\Categories::get_all_for_select');
    //FEATURE SECTION ROUTES
    $routes->add('admin/Featured_sections', 'Admin\Featured_sections::index');
    $routes->add('admin/featured_sections/add_featured_section', 'Admin\Featured_sections::add_featured_section');
    $routes->add('admin/featured_sections/get_custom_services', 'Admin\Featured_sections::get_custom_services');
    $routes->add('admin/featured_sections/list', 'Admin\Featured_sections::list');
    $routes->add('admin/featured_sections/get_section_data', 'Admin\Featured_sections::get_section_data');
    $routes->add('admin/featured_sections/update_featured_section', 'Admin\Featured_sections::update_featured_section');
    $routes->add('admin/featured_sections/delete_featured_section', 'Admin\Featured_sections::delete_featured_section');
    $routes->add('admin/featured-section/change-order', 'Admin\Featured_sections::change_order');
    // PROMOCODE ROUTES
    $routes->add('admin/promo_codes', 'Admin\Promo_codes::index');
    $routes->add('admin/promo_codes/list', 'Admin\Promo_codes::list');
    $routes->add('admin/promo_codes/delete', 'Admin\Promo_codes::delete_promo_code');
    $routes->add('admin/promo_codes/add', 'Admin\Promo_codes::add');
    $routes->add('admin/promo_codes/save', 'Admin\Promo_codes::save');
    $routes->add('admin/promo_codes/update', 'Admin\Promo_codes::update');
    $routes->add('admin/promo_codes/get_promocode_data', 'Admin\Promo_codes::get_promocode_data');
    $routes->add('admin/promo_codes/duplicate/(:any)', 'Admin\Promo_codes::duplicate');
    // SLIDER ROUTES 
    $routes->add('admin/sliders', 'Admin\Sliders::index');
    $routes->add('admin/sliders/list', 'Admin\Sliders::list');
    $routes->add('admin/sliders/add_slider', 'Admin\Sliders::add_slider');
    $routes->add('admin/sliders/update_slider', 'Admin\Sliders::update_slider');
    $routes->add('admin/sliders/delete_sliders', 'Admin\Sliders::delete_sliders');
    $routes->add('admin/sliders/get_providers', 'Admin\Sliders::get_providers');
    //PARTNER ROUTES 
    $routes->add('admin/partners', 'Admin\ProviderController::index');
    $routes->add('admin/partners/list', 'Admin\ProviderController::list');
    $routes->add('admin/partners/add_partner', 'Admin\ProviderController::add_partner');
    $routes->add('admin/partners/provider_leaves', 'Admin\ProviderDetailsController::provider_leaves');
    $routes->add('admin/partners/provider_leaves_list', 'Admin\ProviderDetailsController::provider_leaves_list');
    $routes->add('admin/partners/provider_leaves_calendar', 'Admin\ProviderDetailsController::provider_leaves_calendar');
    $routes->get('admin/partners/provider_ledger', 'Admin\ProviderHistoryController::provider_ledger');
    $routes->get('admin/partners/provider_ledger_details/(:num)', 'Admin\ProviderHistoryController::provider_ledger_details/$1');
    $routes->get('admin/partners/provider_ledger_list/(:num)', 'Admin\ProviderHistoryController::provider_ledger_list/$1');
    $routes->add('admin/partner/insert_partner', 'Admin\ProviderController::insert_partner');
    $routes->add('admin/partners/edit_partner/(:any)', 'Admin\ProviderController::edit_partner');
    $routes->add('admin/partners/update_partner', 'Admin\ProviderController::update_partner');
    $routes->add('admin/partners/general_outlook/(:any)', 'Admin\ProviderDetailsController::general_outlook');
    $routes->add('admin/partners/partner_company_information/(:any)', 'Admin\ProviderController::partner_company_information');
    $routes->add('admin/partners/partner_service_details/(:any)', 'Admin\ProviderDetailsController::partner_service_details');
    $routes->add('admin/partners/partner_order_details/(:any)', 'Admin\ProviderDetailsController::partner_order_details');
    $routes->add('admin/partners/partner_order_details_list/(:any)', 'Admin\ProviderDetailsController::partner_order_details_list');
    $routes->add('admin/partners/partner_promocode_details/(:any)', 'Admin\ProviderDetailsController::partner_promocode_details');
    $routes->add('admin/partners/partner_promocode_details_list/(:any)', 'Admin\ProviderDetailsController::partner_promocode_details_list');
    $routes->add('admin/partners/partner_review_details/(:any)', 'Admin\ProviderDetailsController::partner_review_details');
    $routes->add('admin/partners/partner_review_details_list/(:any)', 'Admin\ProviderDetailsController::partner_review_details_list');
    $routes->add('admin/partners/partner_fetch_sales/(:any)', 'Admin\ProviderDetailsController::partner_fetch_sales');
    $routes->add('admin/partners/remove_seo_image', 'Admin\ProviderDetailsController::remove_seo_image');
    $routes->add('admin/partners/provider_details', 'Admin\ProviderDetailsController::provider_details');
    $routes->add('admin/partners/partner_subscription/(:any)', 'Admin\ProviderSubscriptionController::partner_subscription');
    $routes->get('admin/partners/all_subscription/(:any)', 'Admin\ProviderSubscriptionController::all_subscription_list');
    $routes->add('admin/partners/partner_settlement_and_cash_collection_history/(:any)', 'Admin\ProviderHistoryController::partner_settlement_and_cash_collection_history');
    $routes->add('admin/partners/partner_settlement_and_cash_collection_history_list/(:any)', 'Admin\ProviderHistoryController::partner_settlement_and_cash_collection_history_list');
    $routes->add('admin/partners/partner_handyman_list/(:any)', 'Admin\ProviderDetailsController::partner_handyman_list');
    $routes->add('admin/partners/partner_handyman_list_data/(:any)', 'Admin\ProviderDetailsController::partner_handyman_list_data');
    $routes->post('admin/partners/partner_handyman_toggle_status', 'Admin\ProviderDetailsController::partner_handyman_toggle_status');
    $routes->get('admin/handymen', 'Admin\HandymenController::index');
    $routes->get('admin/handymen/list', 'Admin\HandymenController::list');
    $routes->get('admin/handymen/view/(:num)', 'Admin\HandymenController::view/$1');
    $routes->get('admin/handymen/overview/(:num)', 'Admin\HandymenController::overview/$1');
    $routes->get('admin/handymen/tracker-location/(:num)', 'Admin\HandymenController::trackerLocation/$1');
    $routes->get('admin/handymen/bookings-list/(:num)', 'Admin\HandymenController::bookingsList/$1');
    $routes->get('admin/handymen/reviews-list/(:num)', 'Admin\HandymenController::reviewsList/$1');
    $routes->post('admin/handymen/toggle-status', 'Admin\HandymenController::toggleStatus');
    $routes->post('admin/handymen/delete', 'Admin\HandymenController::delete');
    $routes->add('admin/partners/view_partner/(:any)', 'Admin\ProviderController::view_partner');
    $routes->add('admin/partners/partner_details/(:any)', 'Admin\ProviderDetailsController::partner_details');
    $routes->add('admin/partners/banking_details/(:any)', 'Admin\ProviderController::banking_details');
    $routes->add('admin/partners/timing_details/(:any)', 'Admin\ProviderDetailsController::timing_details');
    $routes->add('admin/partners/service_details/(:any)', 'Admin\ProviderDetailsController::service_details');
    $routes->post('admin/partner/deactivate_partner', 'Admin\ProviderController::deactivate_partner');
    $routes->post('admin/partner/activate_partner', 'Admin\ProviderController::activate_partner');
    $routes->post('admin/partner/approve_partner', 'Admin\ProviderController::approve_partner');
    $routes->post('admin/partner/disapprove_partner', 'Admin\ProviderController::disapprove_partner');
    $routes->post('admin/partner/delete_partner', 'Admin\ProviderController::delete_partner');
    $routes->add('admin/partners/payment_request', 'Admin\ProviderFinanceController::payment_request');
    $routes->add('admin/partners/payment_request_list', 'Admin\ProviderFinanceController::payment_request_list');
    $routes->add('admin/partners/payment_request_multiple_update', 'Admin\ProviderFinanceController::payment_request_multiple_update');
    $routes->add('admin/partners/payment_request_settement_status', 'Admin\ProviderFinanceController::payment_request_settement_status');
    $routes->add('admin/partners/edit_request', 'Admin\ProviderFinanceController::payment_request_list');
    $routes->add('admin/partners/pay_partner', 'Admin\ProviderFinanceController::pay_partner');
    $routes->add('admin/partners/delete_request', 'Admin\ProviderFinanceController::delete_request');
    $routes->add('admin/partners/settle_commission', 'Admin\ProviderFinanceController::settle_commission');
    $routes->add('admin/partners/commission_list', 'Admin\ProviderFinanceController::commission_list');
    $routes->add('admin/partners/bulk_commission_settelement', 'Admin\ProviderFinanceController::bulk_commission_settelement');
    $routes->add('admin/partners/commission_pay_out', 'Admin\ProviderFinanceController::commission_pay_out');
    $routes->add('admin/partners/view_ratings/(:any)', 'Admin\ProviderDetailsController::view_ratings');
    $routes->add('admin/partners/delete_rating', 'Admin\ProviderDetailsController::delete_rating');
    $routes->add('admin/partners/cash_collection', 'Admin\ProviderFinanceController::cash_collection');
    $routes->add('admin/partners/cash_collection_list', 'Admin\ProviderFinanceController::cash_collection_list');
    $routes->add('admin/partners/cash_collection_deduct', 'Admin\ProviderFinanceController::cash_collection_deduct');
    $routes->add('admin/partners/cash_collection_history', 'Admin\ProviderFinanceController::cash_collection_history');
    $routes->add('admin/partners/manage_commission_history', 'Admin\ProviderFinanceController::settle_commission_history');
    $routes->add('admin/partners/manage_commission_history_list', 'Admin\ProviderFinanceController::manage_commission_history_list');
    $routes->add('admin/partners/cash_collection_history_list', 'Admin\ProviderFinanceController::cash_collection_history_list');
    $routes->add('admin/partners/bulk_cash_collection', 'Admin\ProviderFinanceController::bulk_cash_collection');
    $routes->add('admin/partners/duplicate/(:any)', 'Admin\ProviderController::duplicate');
    //USER ROUTES  
    $routes->add('admin/users', 'Admin\Users::index');
    $routes->add('admin/users/deactivate', 'Admin\Users::deactivate');
    $routes->add('admin/users/activate', 'Admin\Users::activate');
    $routes->add('admin/list-user', 'Admin\Users::list_user');
    //ADDRESS ROUTES
    $routes->add('admin/addresses', 'Admin\Addresses::index');
    $routes->add('admin/addresses/list', 'Admin\Addresses::list');

    //SERVIES ROUTES
    $routes->add('admin/services', 'Admin\ServiceController::index');
    $routes->add('admin/services/list', 'Admin\ServiceController::list');
    $routes->add('admin/services/add_service', 'Admin\ServiceController::add_service_view');
    $routes->add('admin/services/insert_service', 'Admin\ServiceController::add_service');
    $routes->add('admin/services/delete_service', 'Admin\ServiceController::delete_service');
    $routes->add('admin/services/edit_service/(:any)', 'Admin\ServiceController::edit_service');
    $routes->add('admin/services/remove_seo_image', 'Admin\ServiceController::remove_seo_image');
    $routes->add('admin/services/update_service', 'Admin\ServiceController::update_service');
    $routes->add('admin/services/service_detail/(:any)', 'Admin\ServiceController::service_detail');
    $routes->post('admin/services/disapprove_service', 'Admin\ServiceController::disapprove_service');
    $routes->post('admin/services/approve_service', 'Admin\ServiceController::approve_service');
    $routes->add('admin/services/duplicate/(:any)', 'Admin\ServiceBulkController::duplicate');
    $routes->add('admin/services/bulk_import_services/', 'Admin\ServiceBulkController::bulk_import_services');
    $routes->add('admin/services/bulk_import_service_upload/', 'Admin\ServiceBulkController::bulk_import_service_upload');
    $routes->add('admin/services/download-sample-for-insert/', 'Admin\ServiceBulkController::downloadSampleForInsert');
    $routes->add('admin/services/download-sample-for-update/', 'Admin\ServiceBulkController::downloadSampleForUpdate');
    $routes->add('admin/services/Service-Add-Instructions/', 'Admin\ServiceBulkController::ServiceAddInstructions');
    $routes->add('admin/services/Service-Update-Instructions/', 'Admin\ServiceBulkController::ServiceUpdateInstructions');




    //ORDERS ROUTE
    $routes->add('admin/orders', 'Admin\Orders::index');
    $routes->add('admin/orders/list', 'Admin\Orders::list');
    $routes->add('admin/orders/veiw_orders/(:any)', 'Admin\Orders::view_orders');
    $routes->add('admin/orders/view_user/(:any)', 'Admin\Orders::view_user');
    $routes->add('admin/orders/view_payment_details/(:any)', 'Admin\Orders::view_payment_details');
    $routes->add('admin/orders/change_order_status', 'Admin\Orders::change_order_status');
    $routes->add('admin/orders/upload_file', 'Admin\Orders::upload_file');
    $routes->add('admin/orders', 'Admin\Orders::index');
    $routes->add('admin/orders/list', 'Admin\Orders::list');
    $routes->add('admin/Orders/delete_orders', 'Admin\Orders::delete_orders');
    $routes->add('admin/orders/invoice/(:any)', 'Admin\Orders::invoice');
    $routes->add('admin/orders/invoice_table/(:any)', 'Admin\Orders::invoice_table');
    $routes->add('admin/orders/customer_details/(:any)', 'Admin\Orders::customer_details');
    $routes->add('admin/orders/payment_details/(:any)', 'Admin\Orders::payment_details');
    $routes->add('admin/orders/partner_details/(:any)', 'Admin\Orders::partner_details');
    $routes->add('admin/orders/view_ordered_services', 'Admin\Orders::view_ordered_services');
    $routes->add('admin/orders/view_ordered_services_list', 'Admin\Orders::view_ordered_services_list');
    $routes->add('admin/orders/cancel_order_service', 'Admin\Orders::cancel_order_service');
    $routes->add('admin/orders/get_slots', 'Admin\Orders::get_slots');
    $routes->post('admin/orders/assign_handyman', 'Admin\Orders::assign_handyman');
    $routes->post('admin/orders/unassign_handyman', 'Admin\Orders::unassign_handyman');
    $routes->post('admin/orders/set_lead_handyman', 'Admin\Orders::set_lead_handyman');
    $routes->get('admin/orders/live_tracking/(:num)', 'Admin\Orders::live_tracking/$1');
    $routes->get('admin/orders/tracker_location/(:num)', 'Admin\Orders::tracker_location/$1');
    $routes->get('admin/orders/orders_calendar', 'Admin\Orders::orders_calendar');
    //FAQS ROUTES
    $routes->add('admin/faqs', 'Admin\Faqs::index');
    $routes->add('admin/faqs/add_faqs', 'Admin\Faqs::add_faqs');
    $routes->add('admin/faqs/list', 'Admin\Faqs::list');
    $routes->add('admin/faqs/remove_faqs', 'Admin\Faqs::remove_faqs');
    $routes->add('admin/faqs/edit_faqs', 'Admin\Faqs::edit_faqs');
    $routes->add('admin/faqs/get_faq_data', 'Admin\Faqs::get_faq_data');
    //NOTIFICATION ROUTES
    $routes->add('admin/notification', 'Admin\Notification::index');
    $routes->add('admin/notification/add_notification', 'Admin\Notification::add_notification');
    $routes->add('admin/notification/delete_notification', 'Admin\Notification::delete_notification');
    $routes->add('admin/notification/list', 'Admin\Notification::list');
    $routes->get('admin/notification/queue-status', 'Admin\Notification::queue_status');


    /**
     * Admin notifications (web panel inbox)
     * ------------------------------------------------
     * Why:
     * - Admin users need the same "bell dropdown + view all" UX as providers.
     * - We intentionally keep this separate from `admin/notification` (send notification page).
     *
     * NOTE:
     * - These endpoints reuse `Notification_model` audience methods (with target = all_users)
     *   so we don't duplicate the "visibility rules" logic.
     */
    $routes->add('admin/notifications', 'Admin\Notification::notifications');
    $routes->get('admin/notifications/recent', 'Admin\Notification::recent');
    $routes->post('admin/notifications/mark_all_read', 'Admin\Notification::mark_all_read');
    $routes->post('admin/notifications/mark_read', 'Admin\Notification::mark_read');
    $routes->post('admin/notifications/validate_redirect_target', 'Admin\Notification::validate_redirect_target');
    $routes->get('admin/notifications/table', 'Admin\Notification::table');
    //TAX ROUTES
    $routes->add('admin/taxes', 'Admin\Tax::index');
    $routes->add('admin/tax/add_tax', 'Admin\Tax::add_tax');
    $routes->add('admin/tax/list', 'Admin\Tax::list');
    $routes->add('admin/tax/edit_taxes', 'Admin\Tax::edit_taxes');
    $routes->add('admin/tax/remove_taxes', 'Admin\Tax::remove_taxes');
    $routes->post('admin/settings/update-tax-settings', 'Admin\Tax::update_tax_settings');
    // SUBSCRIPTION ROUTES
    $routes->add('admin/subscription/', 'Admin\Subscription::index', ['as' => 'admin_subscription']);
    $routes->add('admin/subscription/add_subscription', 'Admin\Subscription::add_subscription');
    $routes->add('admin/subscription/add_store_subscription', 'Admin\Subscription::add_store_subscription');
    $routes->add('admin/subscription/edit_subscription_page/(:any)', 'Admin\Subscription::edit_subscription_page');
    $routes->add('admin/subscription/edit_subscription', 'Admin\Subscription::edit_subscription');
    $routes->add('admin/subscription/delete_subscription', 'Admin\Subscription::delete_subscription');
    $routes->add('admin/subscription/list', 'Admin\Subscription::list');
    $routes->add('admin/add_ons/', 'Admin\Subscription::add_ons_index');
    $routes->add('admin/add_ons/create_add_ons', 'Admin\Subscription::add_on_create_page');
    $routes->add('admin/subscription/subscriber_list', 'Admin\Subscription::subscriber_list');
    $routes->add('admin/subscription/partner_subscriber_list', 'Admin\Subscription::partner_subscription_list');
    $routes->post('admin/assign_subscription_to_partner', 'Admin\ProviderSubscriptionController::assign_subscription_to_partner');
    $routes->post('admin/assign_subscription_to_partner_from_edit_provider', 'Admin\ProviderSubscriptionController::assign_subscription_to_partner_from_edit_provider');
    $routes->post('admin/cancel_subscription_plan', 'Admin\ProviderSubscriptionController::cancel_subscription_plan');
    $routes->post('admin/cancel_subscription_plan_from_edit_partner', 'Admin\ProviderSubscriptionController::cancel_subscription_plan_from_edit_partner');
    $routes->add('admin/transactions', 'Admin\Transactions::index');
    $routes->add('admin/transactions/list-transactions', 'Admin\Transactions::list_transactions');
    //comman routes
    $routes->add('admin/delete_details', 'Admin\Admin::delete_details');
    //SYSTEM USER ROUTE 
    $routes->add('admin/system_users', 'Admin\System_users::index');
    $routes->add('admin/system_users/list', 'Admin\System_users::list');
    $routes->add('admin/system_users/deactivate_user', 'Admin\System_users::deactivate_user');
    $routes->add('admin/system_users/activate_user', 'Admin\System_users::activate_user');
    $routes->add('admin/system_users/delete_user', 'Admin\System_users::delete_user');
    $routes->add('admin/system_users/add_user', 'Admin\System_users::add_user');
    $routes->add('admin/system_users/permit', 'Admin\System_users::permit');
    $routes->add('admin/system_users/edit_permit', 'Admin\System_users::edit_permit');
    $routes->add('save-web-token', 'Admin\Dashboard::save_web_token');
    $routes->add('admin/all_settlement_cashcollection_history', 'Admin\ProviderHistoryController::all_settlement_cashcollection_history');
    $routes->add('admin/all_settlement_cashcollection_history_list', 'Admin\ProviderHistoryController::all_settlement_cashcollection_history_list');
    $routes->add('admin/customer_queris', 'Admin\Dashboard::customer_queris');
    $routes->add('admin/customer_queris_list', 'Admin\Dashboard::customer_queris_list');
    //CHAT ROUTES
    $routes->add('admin/chat', 'Admin\Chats::index');
    $routes->add('admin/store_chat', 'Admin\Chats::store_chat');
    $routes->add('admin/chat_get_all_messages', 'Admin\Chats::getAllMessage');
    $routes->add('admin/get_customers', 'Admin\Chats::get_customers');
    $routes->add('admin/get_providers', 'Admin\Chats::get_providers');
    $routes->add('admin/mark_chat_as_read', 'Admin\Chats::mark_chat_as_read');
    $routes->add('admin/chat_unread_users_count', 'Admin\Chats::unread_users_count');
    $routes->add('admin/settings/email-configuration', 'Admin\EmailSettingsController::email_template_configuration');
    $routes->add('admin/settings/email_template_configuration_update', 'Admin\EmailSettingsController::email_template_configuration_update');
    $routes->add('admin/settings/email_template_list', 'Admin\EmailSettingsController::email_template_list');
    $routes->add('admin/settings/email_template_list_fetch', 'Admin\EmailSettingsController::email_template_list_fetch');
    $routes->add('admin/settings/edit_email_template/(:any)', 'Admin\EmailSettingsController::edit_email_template');
    $routes->add('admin/settings/edit_email_template_operation', 'Admin\EmailSettingsController::edit_email_template_operation');
    $routes->add('admin/settings/delete_email_template', 'Admin\EmailSettingsController::delete_email_template');
    // Email routes
    //NOTIFICATION ROUTES
    $routes->add('admin/send_email_page', 'Admin\SendEmail::index');
    $routes->add('admin/send_email', 'Admin\SendEmail::send_email');
    $routes->add('admin/email_list', 'Admin\SendEmail::list');
    $routes->add('admin/delete_email', 'Admin\SendEmail::delete_email');

    $routes->add('admin/media/upload', 'Admin\Dashboard::upload_media');
    $routes->add('admin/database_backup', 'Admin\DatabaseOperations::index');
    $routes->add('admin/trigger_demo_reset', 'Admin\DatabaseOperations::trigger_demo_reset');
    $routes->post('admin/seed-demo-data', 'Admin\Updater::seed_demo_data');
    $routes->add('admin/clean_database', 'Admin\DatabaseOperations::clean_database_index');
    $routes->add('admin/clean_database_tables', 'Admin\DatabaseOperations::clean_database_tables');
    $routes->add('admin/logs', "Admin\LogViewerController::index");
    $routes->add('admin/partners/bulk_import/', 'Admin\ProviderImportController::bulk_import');
    $routes->add('admin/partners/download-sample-for-insert/', 'Admin\ProviderImportController::downloadSampleForInsert');
    $routes->add('admin/partners/download-sample-for-update/', 'Admin\ProviderImportController::downloadSampleForUpdate');
    $routes->add('admin/partners/bulk_import_provider_upload/', 'Admin\ProviderImportController::bulk_import_provider_upload');

    $routes->add('admin/gallery-view', 'Admin\Gallery::index');
    $routes->add('admin/gallery/get-gallery-files/(:any)', 'Admin\Gallery::GetGallaryFiles');
    $routes->add('admin/gallery/download-all', 'Admin\Gallery::downloadAll');

    $routes->add('admin/custom-job-requests', 'Admin\CustomJobRequest::index');
    $routes->add('admin/custom-job-requests-list', 'Admin\CustomJobRequest::list');

    $routes->add('admin/custom-job/bidders-list/(:any)', 'Admin\CustomJobRequest::bidders_list');
    $routes->add('admin/custom-job/bidders/(:any)', 'Admin\CustomJobRequest::bidders_list_page');
    $routes->add('admin/settings/import_country_codes', 'Admin\CountryCodeController::import_country_codes');

    $routes->add('admin/settings/get_available_countries', 'Admin\CountryCodeController::get_available_countries');


    $routes->add('admin/reason_for_report_and_block_chat', 'Admin\ReasonsForReportAndBlockChat::index');
    $routes->add('admin/reason_for_report_and_block_chat/add', 'Admin\ReasonsForReportAndBlockChat::add');
    $routes->add('admin/reason_for_report_and_block_chat/list', 'Admin\ReasonsForReportAndBlockChat::list');
    $routes->add('admin/edit-rejection-reasons', 'Admin\ReasonsForReportAndBlockChat::edit');
    $routes->add('admin/remove-rejection-reasons', 'Admin\ReasonsForReportAndBlockChat::remove');

    // Custom Pages Management
    $routes->add('admin/custom-pages', 'Admin\CustomPages::index');
    $routes->add('admin/custom-pages/add', 'Admin\CustomPages::add');
    $routes->post('admin/custom-pages/insert', 'Admin\CustomPages::insert');
    $routes->add('admin/custom-pages/edit/(:num)', 'Admin\CustomPages::edit/$1');
    $routes->post('admin/custom-pages/update/(:num)', 'Admin\CustomPages::update/$1');
    $routes->post('admin/custom-pages/delete', 'Admin\CustomPages::delete');
    $routes->post('admin/custom-pages/toggle-status', 'Admin\CustomPages::toggle_status');
    $routes->add('admin/custom-pages/list', 'Admin\CustomPages::list');
    $routes->post('admin/custom-pages/remove-seo-image', 'Admin\CustomPages::remove_seo_image');

    // Blog routes
    $routes->add('admin/blog', 'Admin\Blog::index');
    $routes->add('admin/blog/add-blog', 'Admin\Blog::add_blog_view');
    $routes->add('admin/blog/insert_blog', 'Admin\Blog::add_blog');
    $routes->get('admin/blog/list', 'Admin\Blog::list');
    $routes->add('admin/blog/delete_blog', 'Admin\Blog::delete_blog');
    $routes->add('admin/blog/edit_blog/(:any)', 'Admin\Blog::edit_blog');
    $routes->add('admin/blog/update_blog', 'Admin\Blog::update_blog');
    $routes->add('admin/blog/remove_seo_image', 'Admin\Blog::remove_seo_image');
    $routes->add('admin/blog/add-categories/', 'Admin\Blog::add_blog_categories_view');
    $routes->add('admin/blog/category/add_category', 'Admin\Blog::add_category');
    $routes->add('admin/blog/category/remove_category', 'Admin\Blog::remove_category');
    $routes->add('admin/blog/category/update_category', 'Admin\Blog::update_category');
    $routes->add('admin/blog/categories/list', 'Admin\Blog::list_category');
    $routes->add('admin/blog/get_blog_category_data', 'Admin\Blog::get_blog_category_data');
    $routes->add('admin/blog/get_categories_by_language', 'Admin\Blog::get_categories_by_language');

    //PAYMENT REFUNDS ROUTES
    $routes->add('admin/payment_refunds', 'Admin\PaymentRefunds::index');
    $routes->add('admin/payment_refunds/list', 'Admin\PaymentRefunds::list');
    $routes->add('admin/payment_refunds/getRefundDetails', 'Admin\PaymentRefunds::getRefundDetails');


    $routes->add('admin/payment_refunds/updateStatus', 'Admin\PaymentRefunds::updateStatus');
    // $routes->get('admin/queue/add', 'Admin\QueueController::addToQueue');
    // $routes->get('admin/queue/process', 'Admin\QueueController::processQueue');

    $routes->get('admin/queue/queue1', 'Admin\QueueController::queueNumbers');

    $routes->get('admin/queue/work', 'Admin\QueueController::work');
    $routes->get('admin/queue/stop', 'Admin\QueueController::stopWorker');
    $routes->get('admin/queue/flush', 'Admin\QueueController::flushQueue');

    //CANCELATION REASON ROUTES
    $routes->group('admin/cancellation_reasons', function ($routes) {
        $routes->get('/', 'Admin\CancellationReasons::index');
        $routes->post('save', 'Admin\CancellationReasons::save');
        $routes->get('list', 'Admin\CancellationReasons::list');
        $routes->post('update', 'Admin\CancellationReasons::update');
        $routes->post('delete', 'Admin\CancellationReasons::delete');
    });


    $routes->group('admin/settings/custom_job_settings', function ($routes) {
        $routes->get('/', 'Admin\CustomJobSettings::index');
        $routes->post('save', 'Admin\CustomJobSettings::save');
    });

    //RESCHEDULE REASON ROUTES
    // $routes->group('admin/reschedule_reasons', function ($routes) {
    //     $routes->get('/', 'Admin\RescheduleReasons::index');
    //     $routes->post('save', 'Admin\RescheduleReasons::save');
    //     $routes->get('list', 'Admin\RescheduleReasons::list');
    //     $routes->post('update', 'Admin\RescheduleReasons::update');
    //     $routes->post('delete', 'Admin\RescheduleReasons::delete');
    // });
});

$routes->get('admin/slug/category', 'Admin\SlugController::category');
$routes->get('admin/slug/partner', 'Admin\SlugController::partner');
$routes->get('admin/slug/service', 'Admin\SlugController::service');


$routes->add('admin/get-sql-string', 'Admin\Dashboard::getSQLString');

// $routes->add('admin/dashboard/fetch-country-codes', 'Admin\Dashboard::fetch_and_store_country_codes');

// $routes->add('admin/test', 'Admin\Dashboard::test');