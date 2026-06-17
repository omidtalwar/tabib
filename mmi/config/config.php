<?php
define('BASE_URL', 'http://localhost/mmicollection');
define('SITE_NAME', 'MMI Collection');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Show errors only in development
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
