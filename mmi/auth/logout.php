<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity.php';

if (isset($_SESSION['user_id'])) {
    log_activity($pdo, 'logout', 'Session ended');
}

session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
