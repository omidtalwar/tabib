<?php
/**
 * Activity feed API — called every 5 s by the dashboard terminal.
 * Manual auth check (no require_role) so it doesn't log itself as a page visit.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Unauthorized', 'entries' => [], 'latest_id' => 0]);
    exit;
}

$sinceId = (int)($_GET['since_id'] ?? 0);

try {
    if ($sinceId === 0) {
        // Initial load: most recent 100 entries in chronological order
        $stmt = $pdo->query(
            'SELECT id, user_name, role, action, description,
                    DATE_FORMAT(created_at, "%H:%i:%S") AS time_str
             FROM (
                 SELECT id, user_name, role, action, description, created_at
                 FROM activity_log
                 ORDER BY id DESC
                 LIMIT 100
             ) sub
             ORDER BY id ASC'
        );
        $entries   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $latestId  = empty($entries) ? 0 : (int)end($entries)['id'];

        echo json_encode([
            'entries'   => $entries,
            'latest_id' => $latestId,
            'initial'   => true,
        ]);
    } else {
        // Poll: only new entries since last id
        $stmt = $pdo->prepare(
            'SELECT id, user_name, role, action, description,
                    DATE_FORMAT(created_at, "%H:%i:%S") AS time_str
             FROM activity_log
             WHERE id > ?
             ORDER BY id ASC
             LIMIT 50'
        );
        $stmt->execute([$sinceId]);
        $entries  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $latestId = empty($entries) ? $sinceId : (int)end($entries)['id'];

        echo json_encode([
            'entries'   => $entries,
            'latest_id' => $latestId,
            'initial'   => false,
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['entries' => [], 'latest_id' => $sinceId, 'error' => true]);
}
