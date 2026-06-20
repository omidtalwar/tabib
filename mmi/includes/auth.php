<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/activity.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
    _auto_log_visit();
}

function _auto_log_visit(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (php_sapi_name() === 'cli') return;
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (in_array($script, ['activity_api.php', 'api.php'], true)) return;
    global $pdo;
    if (!isset($pdo)) return;
    $path = $_SERVER['SCRIPT_NAME'] ?? $script;
    $page = ltrim(str_replace('/mmicollection', '', $path), '/');
    log_activity($pdo, 'page_visit', $page);
}

function current_user(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['name']       ?? '',
        'role' => $_SESSION['role']       ?? '',
    ];
}
