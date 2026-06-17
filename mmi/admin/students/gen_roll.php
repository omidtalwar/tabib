<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['roll_no' => '']); exit; }

// Find the highest MMI-##### already issued
$max = $pdo->query("SELECT MAX(CAST(SUBSTRING(roll_no, 5) AS UNSIGNED))
                    FROM students WHERE roll_no REGEXP '^MMI-[0-9]{5}$'")->fetchColumn();

$next = str_pad((int)$max + 1, 5, '0', STR_PAD_LEFT);

echo json_encode(['roll_no' => 'MMI-' . $next]);
