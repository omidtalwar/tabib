<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pageTitle = 'Change Password — ' . SITE_NAME;
$user = current_user();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            log_activity($pdo, 'password_changed', 'Student changed password');
            $success = 'Password changed successfully.';
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Change Password</h1>
        <p class="fluent-caption mt-1">Update your account password.</p>
    </div>

    <div class="max-w-md fluent-fade-in" style="animation-delay:40ms;">
        <div class="fluent-card p-6">

            <?php if ($success): ?>
            <div class="fluent-alert fluent-alert-success mb-5">
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="fluent-alert fluent-alert-danger mb-5">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">

                <div>
                    <label class="fluent-label block mb-1.5">Current Password</label>
                    <div class="fluent-input">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--text-tertiary);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <input type="password" name="current_password" required placeholder="Enter current password">
                    </div>
                </div>

                <div>
                    <label class="fluent-label block mb-1.5">New Password</label>
                    <div class="fluent-input">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--text-tertiary);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <input type="password" name="new_password" required placeholder="Min. 6 characters">
                    </div>
                </div>

                <div>
                    <label class="fluent-label block mb-1.5">Confirm New Password</label>
                    <div class="fluent-input">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--text-tertiary);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <input type="password" name="confirm_password" required placeholder="Repeat new password">
                    </div>
                </div>

                <button type="submit" class="fluent-btn-accent fluent-btn w-full" style="padding:10px 16px;font-size:14px;font-weight:600;">
                    Update Password
                </button>

            </form>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
