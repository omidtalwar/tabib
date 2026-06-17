<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

header('Content-Type: application/json');
$raw = json_decode(file_get_contents('php://input'), true);

$scheduleId = (int)($raw['schedule_id'] ?? 0);
$id         = (int)($raw['id']          ?? 0);
$day        = (int)($raw['day_of_week'] ?? 0);
$start      = trim($raw['time_start']   ?? '');
$end        = trim($raw['time_end']     ?? '');
$subject    = trim($raw['subject']      ?? '');
$teacher    = trim($raw['teacher']      ?? '');
$room       = trim($raw['room']         ?? '');

if (!$scheduleId || !$day || !$start || !$end || !$subject) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}
if (!in_array($day, [1,2,3,4,5,6], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid day.']);
    exit;
}

// Verify schedule belongs to this system
$stmt = $pdo->prepare('SELECT id FROM schedules WHERE id=?');
$stmt->execute([$scheduleId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Schedule not found.']);
    exit;
}

if ($id > 0) {
    $pdo->prepare(
        'UPDATE schedule_slots SET day_of_week=?,time_start=?,time_end=?,subject=?,teacher=?,room=?
         WHERE id=? AND schedule_id=?'
    )->execute([$day, $start, $end, $subject, $teacher ?: null, $room ?: null, $id, $scheduleId]);
} else {
    $pdo->prepare(
        'INSERT INTO schedule_slots (schedule_id,day_of_week,time_start,time_end,subject,teacher,room)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$scheduleId, $day, $start, $end, $subject, $teacher ?: null, $room ?: null]);
}

echo json_encode(['success' => true]);
