<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.phone, u.status,
            t.id AS teacher_id, t.qualification, t.joining_date, t.department
     FROM users u JOIN teachers t ON t.user_id = u.id WHERE u.id = ?'
);
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) { header('Location: index.php'); exit; }

$pageTitle = 'Edit Teacher — ' . SITE_NAME;
$error = $success = '';

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = get_departments($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']          ?? '');
    $email   = trim($_POST['email']         ?? '');
    $phone   = trim($_POST['phone']         ?? '');
    $password= trim($_POST['password']      ?? '');
    $qual    = trim($_POST['qualification'] ?? '');
    $joined  = $_POST['joining_date']       ?? null;
    $status  = isset($_POST['status']) ? 1 : 0;
    $depts   = array_filter(array_map('trim', (array)($_POST['department'] ?? [])));
    $deptStr = implode(',', $depts);

    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } else {
        try {
            $pdo->beginTransaction();
            if ($password) {
                $pdo->prepare('UPDATE users SET name=?,email=?,password=?,phone=?,status=? WHERE id=?')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null, $status, $userId]);
            } else {
                $pdo->prepare('UPDATE users SET name=?,email=?,phone=?,status=? WHERE id=?')
                    ->execute([$name, $email, $phone ?: null, $status, $userId]);
            }
            $pdo->prepare('UPDATE teachers SET qualification=?,joining_date=?,department=? WHERE id=?')
                ->execute([$qual ?: null, $joined ?: null, $deptStr ?: null, $row['teacher_id']]);
            $pdo->commit();
            $stmt->execute([$userId]);
            $row     = $stmt->fetch();
            $success = 'Teacher updated successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Email already in use.' : 'Could not update teacher.';
        }
    }
}
$teacherDepts = array_filter(array_map('trim', explode(',', $row['department'] ?? '')));
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Edit Teacher</h1>
            <p class="fluent-caption mt-1"><?= htmlspecialchars($row['name']) ?></p>
        </div>
        <a href="index.php" class="fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="fluent-card p-6 max-w-lg fluent-fade-in" style="animation-delay:60ms;">
        <form method="POST" class="space-y-5">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Full Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="name" required value="<?= htmlspecialchars($row['name']) ?>">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Phone</label>
                    <div class="fluent-input">
                        <input type="tel" name="phone" placeholder="e.g. 0700000000"
                               value="<?= htmlspecialchars($row['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Email Address *</label>
                <div class="fluent-input">
                    <input type="email" name="email" required value="<?= htmlspecialchars($row['email']) ?>">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">
                    New Password
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;"> — leave blank to keep current</span>
                </label>
                <div class="fluent-input">
                    <input type="password" name="password" minlength="6" placeholder="New password">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Qualification</label>
                <div class="fluent-input">
                    <input type="text" name="qualification"
                           value="<?= htmlspecialchars($row['qualification'] ?? '') ?>"
                           placeholder="e.g. M.Sc. Nursing">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Joining Date</label>
                <div class="fluent-input">
                    <input type="date" name="joining_date"
                           value="<?= htmlspecialchars($row['joining_date'] ?? '') ?>">
                </div>
            </div>

            <!-- Department multi-select -->
            <div>
                <label class="fluent-label block mb-2">Departments</label>
                <div class="flex flex-wrap gap-2" id="deptGroup">
                    <?php foreach ($allDepts as $d): ?>
                    <?php $checked = in_array($d['name_en'], $teacherDepts, true); ?>
                    <label class="dept-pill flex items-center gap-1.5 px-3 py-1.5 rounded-md cursor-pointer transition"
                           style="border:1px solid var(--border);font-size:13px;<?= $checked ? 'background:color-mix(in srgb,var(--accent) 12%,transparent);border-color:var(--accent);color:var(--accent);font-weight:600;' : 'color:var(--text-secondary);' ?>">
                        <input type="checkbox" name="department[]" value="<?= htmlspecialchars($d['name_en']) ?>"
                               class="hidden" <?= $checked ? 'checked' : '' ?>>
                        <?= htmlspecialchars($d['name_en']) ?>
                        <?php if ($d['name_ps']): ?>
                        <span dir="rtl" style="font-size:12px;opacity:.75;"><?= htmlspecialchars($d['name_ps']) ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Active toggle -->
            <div class="flex items-center gap-3">
                <div class="fluent-toggle <?= $row['status'] ? 'on' : '' ?>" id="statusToggle"></div>
                <label style="font-size:14px;color:var(--text);cursor:pointer;">Account active</label>
                <input type="checkbox" name="status" id="statusCheck" value="1"
                       <?= $row['status'] ? 'checked' : '' ?> class="hidden">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="fluent-btn-accent fluent-btn">Save Changes</button>
                <a href="courses.php?id=<?= $userId ?>" class="fluent-btn" style="color:var(--accent);">
                    Manage Courses →
                </a>
            </div>
        </form>
    </div>
</main>

<script>
document.getElementById('statusToggle').addEventListener('click', function() {
    this.classList.toggle('on');
    document.getElementById('statusCheck').checked = this.classList.contains('on');
});

document.querySelectorAll('.dept-pill').forEach(function(pill) {
    pill.querySelector('input[type=checkbox]').addEventListener('change', function() {
        const on = this.checked;
        pill.style.background  = on ? 'color-mix(in srgb,var(--accent) 12%,transparent)' : 'transparent';
        pill.style.borderColor = on ? 'var(--accent)' : 'var(--border)';
        pill.style.color       = on ? 'var(--accent)' : 'var(--text-secondary)';
        pill.style.fontWeight  = on ? '600' : '400';
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
