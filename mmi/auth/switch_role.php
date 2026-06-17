<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$allowed = ['admin', 'teacher', 'student'];
$role    = $_GET['role'] ?? '';

if (!in_array($role, $allowed, true)) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

$_SESSION['role'] = $role;

header('Location: ' . BASE_URL . '/' . $role . '/');
exit;
