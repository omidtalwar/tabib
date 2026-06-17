<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$id      = (int)($_POST['id'] ?? 0);
$adminId = (int)(current_user()['id']);

if (!$id) { header('Location: index.php'); exit; }

$pdo->prepare(
    'UPDATE exam_submissions
     SET status="approved", approved_at=NOW(), approved_by=?
     WHERE id=? AND status="submitted"'
)->execute([$adminId, $id]);

header('Location: index.php?status=approved&approved=1');
exit;
