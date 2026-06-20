<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode([]);
    exit;
}

$dept = trim($_GET['department'] ?? '');
$sem  = trim($_GET['semester']   ?? '');

if (!$dept) {
    echo json_encode([]);
    exit;
}

if ($sem) {
    $stmt = $pdo->prepare('SELECT id, name, credits FROM subjects WHERE department = ? AND semester = ? ORDER BY name');
    $stmt->execute([$dept, $sem]);
} else {
    $stmt = $pdo->prepare('SELECT id, name, credits FROM subjects WHERE department = ? ORDER BY semester, name');
    $stmt->execute([$dept]);
}

echo json_encode($stmt->fetchAll());
