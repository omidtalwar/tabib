<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'My Profile — ' . SITE_NAME;
$user = current_user();

// Load full teacher + user record
$stmt = $pdo->prepare(
    'SELECT u.name, u.email, u.phone, t.id AS teacher_id, t.teacher_no,
            t.qualification, t.department, t.joining_date
     FROM users u JOIN teachers t ON t.user_id = u.id
     WHERE u.id = ?'
);
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    die('Teacher profile not found.');
}

require_once __DIR__ . '/../includes/departments.php';
$allDepts = get_departments($pdo);

$success = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = trim($_POST['phone']        ?? '');
    $depts   = array_filter(array_map('trim', (array)($_POST['department'] ?? [])));
    $deptStr = implode(',', $depts);
    $newPass = trim($_POST['new_password']     ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    // Password validation (only if filled in)
    if ($newPass !== '') {
        if (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (empty($errors)) {
        // Update users table
        if ($newPass !== '') {
            $pdo->prepare('UPDATE users SET phone = ?, password = ? WHERE id = ?')
                ->execute([$phone ?: null, password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
        } else {
            $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')
                ->execute([$phone ?: null, $user['id']]);
        }

        // Update teachers table
        $pdo->prepare('UPDATE teachers SET department = ? WHERE id = ?')
            ->execute([$deptStr ?: null, $profile['teacher_id']]);

        log_activity($pdo, 'profile_updated', 'Teacher updated their profile');

        // Refresh profile
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        $success = 'Profile updated successfully.';
    }
}

// Current departments as array for checkbox pre-check
$currentDepts = array_filter(array_map('trim', explode(',', $profile['department'] ?? '')));
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">My Profile</h1>
        <p class="fluent-caption mt-1">Update your department, contact info, and password.</p>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-5" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="fluent-alert fluent-alert-danger mb-5">
        <?php foreach ($errors as $e): ?>
        <p><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Identity card (read-only) -->
    <div class="fluent-card p-5 mb-5 fluent-fade-in" style="animation-delay:30ms;">
        <div class="flex items-center gap-4">
            <div class="fluent-avatar flex-shrink-0" style="width:56px;height:56px;font-size:22px;">
                <?= strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8')) ?>
            </div>
            <div>
                <p class="fluent-h2 mb-0"><?= htmlspecialchars($user['name']) ?></p>
                <?php if ($profile['teacher_no']): ?>
                <p style="font-size:13px;color:var(--accent);font-family:monospace;margin-top:2px;">
                    <?= htmlspecialchars($profile['teacher_no']) ?>
                </p>
                <?php endif; ?>
                <?php if ($profile['qualification']): ?>
                <p class="fluent-caption"><?= htmlspecialchars($profile['qualification']) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($currentDepts)): ?>
            <div class="flex flex-wrap gap-1 ml-auto">
                <?php foreach ($currentDepts as $d): ?>
                <span class="fluent-badge"><?= htmlspecialchars($d) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" class="max-w-xl space-y-5 fluent-fade-in" style="animation-delay:60ms;">

        <!-- Department -->
        <div class="fluent-card p-5">
            <h2 class="fluent-h2 mb-1">Department <span class="fluent-caption">(optional)</span></h2>
            <p class="fluent-caption mb-4">Select the department(s) you teach in.</p>
            <div class="grid grid-cols-2 gap-2">
                <?php foreach ($allDepts as $d): ?>
                <label style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:6px;
                              border:1px solid var(--border);cursor:pointer;
                              background:<?= in_array($d['name_en'], $currentDepts) ? 'color-mix(in srgb,var(--accent) 8%,var(--surface))' : 'var(--surface)' ?>;
                              transition:background .15s;">
                    <input type="checkbox" name="department[]"
                           value="<?= htmlspecialchars($d['name_en']) ?>"
                           <?= in_array($d['name_en'], $currentDepts) ? 'checked' : '' ?>
                           style="accent-color:var(--accent);width:14px;height:14px;">
                    <span style="font-size:13px;"><?= htmlspecialchars($d['name_en']) ?></span>
                    <?php if ($d['name_ps']): ?>
                    <span style="font-size:11px;color:var(--text-tertiary);margin-left:auto;"><?= htmlspecialchars($d['name_ps']) ?></span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Phone -->
        <div class="fluent-card p-5">
            <h2 class="fluent-h2 mb-1">Phone <span class="fluent-caption">(optional)</span></h2>
            <div class="fluent-input mt-3">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     style="color:var(--text-tertiary);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
                       placeholder="e.g. 07xxxxxxxx">
            </div>
        </div>

        <!-- Password -->
        <div class="fluent-card p-5">
            <h2 class="fluent-h2 mb-1">Change Password <span class="fluent-caption">(optional)</span></h2>
            <p class="fluent-caption mb-4">Leave blank to keep your current password.</p>
            <div class="space-y-3">
                <div>
                    <label class="fluent-label block mb-1.5">New Password</label>
                    <div class="fluent-input">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--text-tertiary);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <input type="password" name="new_password" placeholder="Min. 6 characters" autocomplete="new-password">
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
                        <input type="password" name="confirm_password" placeholder="Repeat new password" autocomplete="new-password">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:14px;padding:10px 24px;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Save Changes
        </button>
    </form>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
