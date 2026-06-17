<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        // Allow login by email, student roll no, or teacher ID
        $stmt = $pdo->prepare(
            'SELECT u.* FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN teachers t ON t.user_id = u.id
             WHERE (
                 u.email = ?
                 OR (s.roll_no   = ? AND u.role = "student")
                 OR (t.teacher_no = ? AND u.role = "teacher")
             ) AND u.status = 1 LIMIT 1'
        );
        $stmt->execute([$email, $email, $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            log_activity($pdo, 'login', 'Signed in as ' . $user['role'],
                         (int)$user['id'], $user['name'], $user['role']);
            header('Location: ' . BASE_URL . '/' . $user['role'] . '/');
            exit;
        }
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — <?= SITE_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fluent-vibe.css">
</head>
<body class="fluent fluent-mica min-h-screen flex items-center justify-center p-4">

<div class="w-full flex max-w-5xl" style="min-height: 580px; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-lg);">

    <!-- Left panel — accent -->
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-10"
         style="background: linear-gradient(140deg, var(--accent) 0%, #0a3f78 100%); position: relative; overflow: hidden;">

        <!-- Mica overlay -->
        <div style="position:absolute;inset:0;background:radial-gradient(ellipse 600px 400px at 0% 0%, rgba(255,255,255,0.06),transparent 60%),radial-gradient(ellipse 400px 300px at 100% 100%,rgba(255,255,255,0.04),transparent 60%);"></div>

        <div style="position:relative;">
            <div class="flex items-center gap-3 mb-8">
                <div style="background:rgba(255,255,255,0.2);border-radius:8px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <svg class="w-6 h-6" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <span style="color:white;font-size:18px;font-weight:700;letter-spacing:0.01em;"><?= SITE_NAME ?></span>
            </div>

            <h1 style="color:white;font-size:32px;font-weight:700;line-height:1.2;margin-bottom:12px;">
                Institute<br>Management<br>System
            </h1>
            <p style="color:rgba(255,255,255,0.7);font-size:14px;line-height:1.6;max-width:280px;">
                Manage teachers, students, courses and academic data in one place.
            </p>
        </div>

        <!-- Feature list -->
        <div style="position:relative;" class="space-y-3">
            <?php foreach ([
                ['Admin Control', 'Full management access'],
                ['Teacher Portal', 'Courses & student management'],
                ['Student View',   'Courses, schedule & materials'],
            ] as [$title, $sub]): ?>
            <div class="flex items-center gap-3">
                <div style="width:8px;height:8px;background:rgba(255,255,255,0.5);border-radius:50%;flex-shrink:0;"></div>
                <div>
                    <p style="color:white;font-size:13px;font-weight:600;"><?= $title ?></p>
                    <p style="color:rgba(255,255,255,0.6);font-size:12px;"><?= $sub ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <p style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:16px;">
                © <?= date('Y') ?> <?= SITE_NAME ?>
            </p>
        </div>
    </div>

    <!-- Right panel — form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12"
         style="background: var(--surface);">
        <div class="w-full max-w-sm">

            <!-- Icon -->
            <div class="mb-6 flex justify-center">
                <div class="fluent-avatar" style="width:56px;height:56px;font-size:24px;">
                    <svg class="w-7 h-7" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
            </div>

            <h2 class="fluent-h2 text-center mb-1">Welcome back</h2>
            <p class="fluent-caption text-center mb-6">Sign in to your account to continue</p>

            <?php if ($error): ?>
            <div class="fluent-alert fluent-alert-danger mb-4">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="fluent-label block mb-1.5">Email / Student ID / Teacher ID</label>
                    <div class="fluent-input">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--text-tertiary);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <input type="text" name="email" required placeholder="Email, MMI-00001 or TCH-0001"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               autocomplete="username" autocapitalize="off" spellcheck="false">
                    </div>
                </div>

                <div>
                    <label class="fluent-label block mb-1.5">Password</label>
                    <div class="fluent-input">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--text-tertiary);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="fluent-btn-accent fluent-btn w-full mt-2" style="padding: 10px 16px; font-size:14px; font-weight:600;">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
