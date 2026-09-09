<?php

/**
 * Image Path Constants
 * 
 * This file contains constants for image upload paths used throughout the application
 * Centralized location for managing image storage paths
 */

// Language image upload paths
define('LANGUAGE_IMAGE_UPLOAD_PATH', 'public/uploads/languages/images/');
define('LANGUAGE_IMAGE_DB_PATH', 'languages/');
define('LANGUAGE_IMAGE_URL_PATH', 'public/uploads/languages/images/');

// Full system paths
define('LANGUAGE_IMAGE_FULL_PATH', defined('FCPATH') ? FCPATH . LANGUAGE_IMAGE_UPLOAD_PATH : __DIR__ . '/../../' . LANGUAGE_IMAGE_UPLOAD_PATH);

// Allowed image extensions for language uploads
define('LANGUAGE_IMAGE_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

// Maximum file size for language images (in bytes) - 2MB
define('IMAGE_MAX_SIZE', 2 * 1024 * 1024);
