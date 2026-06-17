<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$userId  = (int)$_SESSION['user_id'];
$sinceTs = (int)($_GET['since_ts'] ?? 0);
// Default: show last 7 days if no since_ts given
if ($sinceTs <= 0) $sinceTs = time() - (7 * 86400);
$sinceDate = date('Y-m-d H:i:s', $sinceTs);

// Get student profile (dept/sem/shift)
$stmt = $pdo->prepare('SELECT department, semester, shift FROM students WHERE user_id = ?');
$stmt->execute([$userId]);
$student = $stmt->fetch();

$notifications = [];

if ($student && $student['department']) {
    $dept  = $student['department'];
    $sem   = $student['semester'];
    $shift = $student['shift'];

    // ── New materials ────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT m.id, m.title, m.created_at, u.name AS teacher_name, tc.subject_name
         FROM materials m
         LEFT JOIN teachers t ON t.id = m.teacher_id
         LEFT JOIN users u ON u.id = t.user_id
         LEFT JOIN teacher_courses tc ON tc.id = m.course_id
         WHERE (m.course_id IS NULL OR (tc.department = ? AND tc.semester = ? AND tc.shift = ?))
           AND m.created_at > ?
         ORDER BY m.created_at DESC
         LIMIT 15'
    );
    $stmt->execute([$dept, $sem, $shift, $sinceDate]);
    foreach ($stmt->fetchAll() as $row) {
        $notifications[] = [
            'type'    => 'material',
            'title'   => $row['title'],
            'desc'    => ($row['teacher_name'] ? $row['teacher_name'] : 'Your teacher') .
                         ($row['subject_name'] ? ' · ' . $row['subject_name'] : ''),
            'url'     => 'materials.php',
            'ts'      => strtotime($row['created_at']),
        ];
    }

    // ── Newly approved exam results ─────────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT sub.id, tc.subject_name, sub.exam_type, sub.approved_at
         FROM exam_submissions sub
         JOIN teacher_courses tc ON tc.id = sub.teacher_course_id
         WHERE tc.department = ? AND tc.semester = ? AND tc.shift = ?
           AND sub.status = "approved"
           AND sub.approved_at > ?
         ORDER BY sub.approved_at DESC
         LIMIT 10'
    );
    $stmt->execute([$dept, $sem, $shift, $sinceDate]);
    foreach ($stmt->fetchAll() as $row) {
        $examLabel = ucfirst($row['exam_type']) . ' exam';
        $notifications[] = [
            'type'  => 'result',
            'title' => $row['subject_name'] . ' result published',
            'desc'  => $examLabel . ' scores are now available',
            'url'   => 'scores.php',
            'ts'    => strtotime($row['approved_at']),
        ];
    }
}

// Sort newest first
usort($notifications, fn($a, $b) => $b['ts'] - $a['ts']);

// Time-ago helper
function timeAgo(int $ts): string {
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return (int)($diff/60) . 'm ago';
    if ($diff < 86400)  return (int)($diff/3600) . 'h ago';
    return (int)($diff/86400) . 'd ago';
}

foreach ($notifications as &$n) {
    $n['time_ago'] = timeAgo($n['ts']);
}

echo json_encode(['notifications' => $notifications, 'count' => count($notifications)]);
