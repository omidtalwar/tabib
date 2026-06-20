<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare('SELECT subject_name FROM exam_schedules WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare('DELETE FROM exam_schedules WHERE id=?')->execute([$id]);
        log_activity($pdo, 'exam_schedule_deleted', 'Deleted exam: ' . $row['subject_name']);
    }
}
$redirect = $_POST['redirect'] ?? 'index.php?tab=exam_schedule';
header('Location: ' . $redirect);
exit;
