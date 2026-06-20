<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $pdo->prepare('DELETE FROM schedules WHERE id=?')->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Schedule deleted successfully.'];
}
header('Location: index.php');
