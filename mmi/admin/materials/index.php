<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/shamsi.php';
require_role('admin');

$pageTitle = 'Materials — ' . SITE_NAME;
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $stmt  = $pdo->prepare('SELECT file_path FROM materials WHERE id=?');
    $stmt->execute([$delId]);
    $mat = $stmt->fetch();
    if ($mat && $mat['file_path'] && file_exists(UPLOAD_DIR . $mat['file_path'])) {
        unlink(UPLOAD_DIR . $mat['file_path']);
    }
    $pdo->prepare('DELETE FROM materials WHERE id=?')->execute([$delId]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Material deleted.'];
    header('Location: index.php'); exit;
}

if (isset($_SESSION['flash'])) {
    $success = $_SESSION['flash']['msg'] ?? '';
    unset($_SESSION['flash']);
}

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = dept_names_en($pdo);

// ── Filters ───────────────────────────────────────────────────────────────────
$fQ     = trim($_GET['q']          ?? '');
$fDept  = trim($_GET['department'] ?? '');
$fSem   = trim($_GET['semester']   ?? '');

$where  = ['1=1'];
$params = [];

if ($fQ) {
    $where[]  = '(m.title LIKE ? OR u.name LIKE ? OR tc.subject_name LIKE ?)';
    $like     = '%' . $fQ . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($fDept) {
    $where[]  = 'tc.department = ?';
    $params[] = $fDept;
}
if ($fSem) {
    $where[]  = 'tc.semester = ?';
    $params[] = $fSem;
}

$stmt = $pdo->prepare(
    'SELECT m.id, m.title, m.description, m.file_path, m.created_at, m.due_date,
            u.name AS teacher_name,
            tc.subject_name, tc.department, tc.semester, tc.shift
     FROM materials m
     JOIN teachers t ON t.id = m.teacher_id
     JOIN users u ON u.id = t.user_id
     LEFT JOIN teacher_courses tc ON tc.id = m.course_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY m.created_at DESC
     LIMIT 300'
);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$semOptions = [];
for ($i = 1; $i <= 6; $i++) {
    $suf = $i <= 3 ? ['','st','nd','rd'][$i] : 'th';
    $semOptions[] = $i . $suf . ' Semester';
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Materials</h1>
            <p class="fluent-caption mt-1"><?= count($materials) ?> material<?= count($materials) !== 1 ? 's' : '' ?> found</p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Filter bar -->
    <form method="GET" class="fluent-card p-4 mb-5 fluent-fade-in" style="animation-delay:40ms;">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">

            <!-- Search -->
            <div class="md:col-span-2">
                <label class="fluent-label block mb-1">Search</label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="<?= htmlspecialchars($fQ) ?>"
                           placeholder="Title, teacher name, subject…">
                </div>
            </div>

            <!-- Department -->
            <div>
                <label class="fluent-label block mb-1">Department</label>
                <div class="fluent-input">
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($allDepts as $d): ?>
                        <option <?= $fDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Semester -->
            <div>
                <label class="fluent-label block mb-1">Semester</label>
                <div class="fluent-input">
                    <select name="semester">
                        <option value="">All Semesters</option>
                        <?php foreach ($semOptions as $s): ?>
                        <option <?= $fSem === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-3">
            <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:13px;">Apply</button>
            <?php if ($fQ || $fDept || $fSem): ?>
            <a href="index.php" class="fluent-btn" style="font-size:13px;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Teacher</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th style="text-align:center;">File</th>
                    <th>Closing Date</th>
                    <th>Uploaded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($materials)): ?>
            <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:var(--text-tertiary);">
                    No materials found<?= ($fQ || $fDept || $fSem) ? ' for the selected filters.' : '.' ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($materials as $m):
                $hasDue    = !empty($m['due_date']);
                $isOverdue = $hasDue && strtotime($m['due_date']) < strtotime('today');
            ?>
            <tr>
                <td>
                    <p style="font-weight:600;"><?= htmlspecialchars($m['title']) ?></p>
                    <?php if ($m['description']): ?>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                        <?= htmlspecialchars(mb_substr($m['description'], 0, 70)) . (mb_strlen($m['description']) > 70 ? '…' : '') ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($m['subject_name']): ?>
                    <span class="fluent-badge" style="font-size:10px;margin-top:3px;display:inline-block;">
                        <?= htmlspecialchars($m['subject_name']) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-secondary);font-size:13px;"><?= htmlspecialchars($m['teacher_name']) ?></td>
                <td style="font-size:13px;">
                    <?php if ($m['department']): ?>
                    <?= htmlspecialchars($m['department']) ?>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);">General</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;color:var(--text-secondary);">
                    <?= $m['semester'] ? htmlspecialchars($m['semester']) : '—' ?>
                    <?php if ($m['shift']): ?>
                    <br><span style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($m['shift']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($m['file_path']): ?>
                    <?php $ext = strtoupper(pathinfo($m['file_path'], PATHINFO_EXTENSION)); ?>
                    <a href="<?= UPLOAD_URL . htmlspecialchars($m['file_path']) ?>" target="_blank"
                       class="fluent-badge" style="text-decoration:none;"><?= $ext ?></a>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($hasDue): ?>
                    <span style="font-size:12px;font-weight:600;color:<?= $isOverdue ? '#c42b1c' : '#107c10' ?>;">
                        <?= shamsiDate($m['due_date'] . ' 00:00:00') ?>
                    </span>
                    <?php if ($isOverdue): ?>
                    <br><span style="font-size:10px;color:#c42b1c;">Closed</span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-tertiary);font-size:12px;">
                    <?= shamsiDate($m['created_at']) ?>
                </td>
                <td>
                    <form method="POST" onsubmit="return confirm('Delete this material?')">
                        <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="fluent-btn"
                                style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
