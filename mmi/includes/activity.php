<?php
/**
 * Log a system activity.
 * Falls back to session for user info if not explicitly passed.
 * Silently fails so it never breaks the app.
 */
function log_activity(PDO $pdo, string $action, string $description = '',
                      ?int $uid = null, ?string $uname = null, ?string $role = null): void
{
    try {
        $uid   = $uid   ?? ($_SESSION['user_id'] ?? null);
        $uname = $uname ?? ($_SESSION['name']    ?? 'System');
        $role  = $role  ?? ($_SESSION['role']    ?? 'system');
        $ip    = $_SERVER['REMOTE_ADDR'] ?? null;

        $pdo->prepare(
            'INSERT INTO activity_log (user_id, user_name, role, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uid, $uname, $role, $action, $description, $ip]);
    } catch (Exception $e) { /* silent — logging must never break the app */ }
}
