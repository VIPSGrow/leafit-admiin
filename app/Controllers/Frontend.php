<?php

namespace App\Controllers;

use App\Controllers\BaseController;



/**
 * Baseclass or Parent class for all admin controllers.
 */
class Frontend extends BaseController
{
    protected $settings, $appName;

    public function __construct()
    {
        $this->settings = get_settings("general_settings", true);
        $this->appName = (isset($this->settings['company_title'])) ? $this->settings['company_title'] : "eDemand";
    }

    /**
     * Serve Firebase service worker dynamically with values from database
     * 
     * This method generates the Firebase messaging service worker JavaScript
     * file dynamically by fetching Firebase configuration from the database.
     * This allows Firebase settings to be managed through the admin panel
     * without needing to manually edit the service worker file.
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function firebaseServiceWorker()
    {
        // Get Firebase settings from database
        // The get_settings function retrieves settings from the settings table
        $firebaseSettings = get_settings('firebase_settings', true);

        // Set proper headers for JavaScript content
        // Service workers must be served with application/javascript content type
        $response = $this->response;
        $response->setHeader('Content-Type', 'application/javascript');
        $response->setHeader('Service-Worker-Allowed', '/');

        // Set cache headers to allow browser caching but ensure updates are fetched
        // Cache for 1 hour, but allow revalidation
        $response->setHeader('Cache-Control', 'public, max-age=3600, must-revalidate');

        // Extract Firebase configuration values from database
        // Provide default empty strings if values are not set to prevent JavaScript errors
        $apiKey = isset($firebaseSettings['apiKey']) ? $firebaseSettings['apiKey'] : '';
        $authDomain = isset($firebaseSettings['authDomain']) ? $firebaseSettings['authDomain'] : '';
        $projectId = isset($firebaseSettings['projectId']) ? $firebaseSettings['projectId'] : '';
        $storageBucket = isset($firebaseSettings['storageBucket']) ? $firebaseSettings['storageBucket'] : '';
        $messagingSenderId = isset($firebaseSettings['messagingSenderId']) ? $firebaseSettings['messagingSenderId'] : '';
        $appId = isset($firebaseSettings['appId']) ? $firebaseSettings['appId'] : '';
        $measurementId = isset($firebaseSettings['measurementId']) ? $firebaseSettings['measurementId'] : '';
        $vapidKey = isset($firebaseSettings['vapidKey']) ? $firebaseSettings['vapidKey'] : '';

        // Generate the service worker JavaScript content
        // This replaces the hardcoded values with database values
        $serviceWorkerContent = <<<JS
            importScripts('https://www.gstatic.com/firebasejs/8.2.0/firebase.js');
            importScripts('https://www.gstatic.com/firebasejs/8.2.0/firebase-app.js');
            importScripts('https://www.gstatic.com/firebasejs/8.2.0/firebase-messaging.js');

            // Firebase configuration loaded from database
            // These values are dynamically generated from the firebase_settings in the database
            const config = {
            apiKey: "{$apiKey}",
            authDomain: "{$authDomain}",
            projectId: "{$projectId}",
            storageBucket: "{$storageBucket}",
            messagingSenderId: "{$messagingSenderId}",
            appId: "{$appId}",
            measurementId: "{$measurementId}"
            };

            // Initialize Firebase with the configuration from database
            firebase.initializeApp(config);

            // Get Firebase Cloud Messaging instance
            const fcm = firebase.messaging();

            // Request FCM token with VAPID key from database
            fcm.getToken({
                vapidKey: "{$vapidKey}"
            }).then((token) => {
                // Token retrieved successfully
                // The token can be used to send push notifications to this device
            });

            // Handle background messages when app is not in foreground
            fcm.onBackgroundMessage((data) => {
                // Handle background push notifications here
                // This is called when a push notification is received while the app is in the background
            });
        JS;

        // Return the generated JavaScript content
        return $response->setBody($serviceWorkerContent);
    }
}
function get_settings($type = 'system_settings', $is_json = false, $bool = false)
{
    $db      = \Config\Database::connect();
    $builder = $db->table('settings');
    $res = $builder->select(' * ')->where('variable', $type)->get()->getResultArray();
    if (!empty($res)) {
        if ($is_json) {
            return json_decode($res[0]['value'], true);
        } else {
            return $res[0]['value'];
        }
    } else {
        if ($bool) {

            return false;
        } else {
            return [];
        }
    }
}
