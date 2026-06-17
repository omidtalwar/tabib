<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
// Good working...