<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

header('Content-Type: application/json');
$raw        = json_decode(file_get_contents('php://input'), true);
$id         = (int)($raw['id']          ?? 0);
$scheduleId = (int)($raw['schedule_id'] ?? 0);

if (!$id || !$scheduleId) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$pdo->prepare('DELETE FROM schedule_slots WHERE id=? AND schedule_id=?')
    ->execute([$id, $scheduleId]);

echo json_encode(['success' => true]);
