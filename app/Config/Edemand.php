<?php

namespace Config;

class Edemand extends \CodeIgniter\Config\BaseConfig
{
    public $permissions  = [
        'orders' =>  array('read', 'update', 'delete'),
        'categories' =>  array('create', 'read', 'update', 'delete'),
        'subscription' =>  array('create', 'read', 'update', 'delete'),
        'sliders' =>  array('create', 'read', 'update', 'delete'),
        'tax' => array('create', 'read', 'update', 'delete'),
        'services' => array('create', 'read', 'update', 'delete'),
        'promo_code' => array('create', 'read', 'update', 'delete'),
        'featured_section' => array('create', 'read', 'update', 'delete'),
        'partner' => array('create', 'read', 'update', 'delete'),
        'customers' => array('read', 'update'),
        'send_notification' => array('create', 'read', 'delete'),
        'email_notifications' => array('create', 'read', 'delete'),
        'faq' => array('create', 'read', 'update', 'delete'),
        'system_update' => array('update'),
        'settings' => array('create', 'read', 'update'),
        'system_user' => array('create', 'read', 'update', 'delete'),
        'seo_settings' => array('create', 'read', 'update', 'delete'),
        'blog' => array('create', 'read', 'update', 'delete'),
        // Support management permissions
        'customer_queries' => array('read', 'update', 'delete'),
        'chat' => array('read', 'delete'),
        'user_reports' => array('read', 'update', 'delete'),
        'reporting_reasons' => array('create', 'read', 'update', 'delete'),
        // Handyman management
        'handyman' => array('read', 'update'),
        // Partner management additional permissions
        'payment_request' => array('read', 'update'),
        'settlement' => array('read', 'update'),
        'cash_collection' => array('read', 'update'),
        // Booking management additional permissions
        'booking_payment' => array('read'),
        'custom_job_requests' => array('read', 'update', 'delete'),
        // Media and system permissions
        'gallery' => array('create', 'read', 'update', 'delete'),
        'database_backup' => array('read', 'delete'),
        'custom_pages' => array('create', 'read', 'update', 'delete'),
        'custom_fields' => array('create', 'read', 'update', 'delete'),
    ];
}
