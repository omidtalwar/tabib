<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $pdo->prepare(
        'UPDATE exam_submissions
         SET status="draft", submitted_at=NULL, approved_at=NULL, approved_by=NULL
         WHERE id=?'
    )->execute([$id]);
    log_activity($pdo, 'exam_reopened', 'Submission #' . $id . ' reopened for editing');
}

header('Location: index.php?reopened=1&status=all');
