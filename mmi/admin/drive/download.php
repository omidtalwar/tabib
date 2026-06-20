<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM drive_files WHERE id = ?');
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) { header('Location: index.php'); exit; }

$fullPath = UPLOAD_DIR . $file['file_path'];
if (!file_exists($fullPath)) {
    http_response_code(404);
    die('File not found on server.');
}

log_activity($pdo, 'drive_file_downloaded', $file['name']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-cache');
readfile($fullPath);
exit;
