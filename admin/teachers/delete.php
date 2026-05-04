<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$userId = (int)($_POST['id'] ?? 0);
if (!$userId) {
    header('Location: index.php');
    exit;
}

// Prevent deleting your own account
if ($userId === (int)$_SESSION['user_id']) {
    header('Location: index.php?error=self');
    exit;
}

$pdo->prepare('DELETE FROM users WHERE id = ? AND role = "teacher"')->execute([$userId]);

header('Location: index.php?deleted=1');
exit;
