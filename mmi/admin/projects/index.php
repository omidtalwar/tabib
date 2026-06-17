<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/shamsi.php';
require_role('admin');

$pageTitle = 'Student Projects — ' . SITE_NAME;

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = dept_names_en($pdo);
$shifts   = ['06:00 – 09:00','09:00 – 12:00','01:00 – 04:00'];
$statuses = ['submitted','reviewed','returned'];

// ── Handle status update / note ───────────────────────────────────────────────
$flashMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $uid    = (int)$_POST['update_id'];
    $status = in_array($_POST['status'] ?? '', $statuses) ? $_POST['status'] : 'submitted';
    $note   = trim($_POST['admin_note'] ?? '');
    $pdo->prepare('UPDATE student_projects SET status=?, admin_note=? WHERE id=?')
        ->execute([$status, $note ?: null, $uid]);
    $flashMsg = 'Project updated.';
    log_activity($pdo, 'project_' . $status, 'Project #' . $uid);
}

// ── Filters ───────────────────────────────────────────────────────────────────
$fDept   = trim($_GET['department'] ?? '');
$fSem    = trim($_GET['semester']   ?? '');
$fShift  = trim($_GET['shift']      ?? '');
$fStatus = trim($_GET['status']     ?? '');
$fSearch = trim($_GET['search']     ?? '');

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($fDept)   { $where[] = 's.department = ?';  $params[] = $fDept; }
if ($fSem)    { $where[] = 's.semester = ?';    $params[] = $fSem; }
if ($fShift)  { $where[] = 's.shift = ?';       $params[] = $fShift; }
if ($fStatus) { $where[] = 'p.status = ?';      $params[] = $fStatus; }
if ($fSearch) {
    $where[]  = '(u.name LIKE ? OR s.roll_no LIKE ? OR p.title LIKE ? OR p.subject LIKE ?)';
    $like     = '%' . $fSearch . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$projects = $pdo->prepare(
    'SELECT p.*, u.name AS student_name, s.roll_no, s.father_name,
            s.department, s.semester, s.shift
     FROM student_projects p
     JOIN students s ON s.id = p.student_id
     JOIN users u ON u.id = s.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.created_at DESC'
);
$projects->execute($params);
$projects = $projects->fetchAll();

// ── Stats ────────────────────────────────────────────────────────────────────
$stats = $pdo->query(
    'SELECT status, COUNT(*) AS cnt FROM student_projects GROUP BY status'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$statusMeta = [
    'submitted' => ['label' => 'Submitted', 'color' => '#1b6ec2'],
    'reviewed'  => ['label' => 'Reviewed',  'color' => '#107c10'],
    'returned'  => ['label' => 'Returned',  'color' => '#c42b1c'],
];
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Header -->
    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Student Projects</h1>
            <p class="fluent-caption mt-1"><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?> found</p>
        </div>
    </div>

    <?php if ($flashMsg): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="grid grid-cols-3 gap-3 mb-5 fluent-fade-in" style="animation-delay:20ms;">
        <?php foreach ($statusMeta as $key => $meta): ?>
        <div class="fluent-card p-4 flex items-center gap-3">
            <div style="width:10px;height:36px;border-radius:4px;background:<?= $meta['color'] ?>;flex-shrink:0;"></div>
            <div>
                <p style="font-size:22px;font-weight:700;color:var(--text);line-height:1;"><?= (int)($stats[$key] ?? 0) ?></p>
                <p class="fluent-caption"><?= $meta['label'] ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="fluent-card p-4 mb-5 fluent-fade-in" style="animation-delay:40ms;">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">

            <div class="md:col-span-2">
                <label class="fluent-label block mb-1">Search</label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($fSearch) ?>"
                           placeholder="Student name, roll no, project title…">
                </div>
            </div>

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

            <div>
                <label class="fluent-label block mb-1">Semester</label>
                <div class="fluent-input">
                    <select name="semester">
                        <option value="">All Semesters</option>
                        <?php for ($i = 1; $i <= 6; $i++):
                            $suf = $i <= 3 ? ['','st','nd','rd'][$i] : 'th';
                            $lbl = $i . $suf . ' Semester';
                        ?>
                        <option <?= $fSem === $lbl ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1">Shift</label>
                <div class="fluent-input">
                    <select name="shift">
                        <option value="">All Shifts</option>
                        <?php foreach ($shifts as $sh): ?>
                        <option <?= $fShift === $sh ? 'selected' : '' ?>><?= $sh ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 mt-3">
            <!-- Status pills -->
            <?php foreach ($statusMeta as $key => $meta): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;
                          padding:4px 12px;border-radius:20px;border:1px solid;transition:all 120ms;
                          border-color:<?= $fStatus === $key ? $meta['color'] : 'var(--border)' ?>;
                          background:<?= $fStatus === $key ? 'color-mix(in srgb,' . $meta['color'] . ' 12%,transparent)' : 'transparent' ?>;
                          color:<?= $fStatus === $key ? $meta['color'] : 'var(--text-secondary)' ?>;font-weight:<?= $fStatus === $key ? '600' : '400' ?>;">
                <input type="radio" name="status" value="<?= $key ?>"
                       <?= $fStatus === $key ? 'checked' : '' ?> class="hidden">
                <?= $meta['label'] ?> (<?= (int)($stats[$key] ?? 0) ?>)
            </label>
            <?php endforeach; ?>
            <?php if ($fStatus): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>"
               style="font-size:12px;color:var(--text-tertiary);">× All statuses</a>
            <?php endif; ?>

            <div class="flex-1"></div>
            <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:13px;">Apply</button>
            <?php if ($fDept || $fSem || $fShift || $fStatus || $fSearch): ?>
            <a href="index.php" class="fluent-btn" style="font-size:13px;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Projects table -->
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:80ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Project</th>
                    <th>Dept / Semester</th>
                    <th>Shift</th>
                    <th style="text-align:center;">File</th>
                    <th>Submitted</th>
                    <th style="text-align:center;">Status</th>
                    <th style="width:80px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($projects)): ?>
            <tr>
                <td colspan="8" style="text-align:center;padding:48px;color:var(--text-tertiary);">
                    No projects match your filters.
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($projects as $p):
                $sm = $statusMeta[$p['status']] ?? $statusMeta['submitted'];
                $ext = $p['file_path'] ? strtoupper(pathinfo($p['file_path'], PATHINFO_EXTENSION)) : '';
            ?>
            <tr>
                <td>
                    <div class="flex items-center gap-2">
                        <div class="fluent-avatar" style="width:30px;height:30px;font-size:11px;flex-shrink:0;">
                            <?= strtoupper(substr($p['student_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['student_name']) ?></p>
                            <?php if ($p['roll_no']): ?>
                            <p style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($p['roll_no']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <p style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['title']) ?></p>
                    <?php if ($p['subject']): ?>
                    <p style="font-size:11px;color:var(--accent);margin-top:1px;"><?= htmlspecialchars($p['subject']) ?></p>
                    <?php endif; ?>
                    <?php if ($p['description']): ?>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-top:1px;">
                        <?= htmlspecialchars(mb_substr($p['description'], 0, 60)) ?>…
                    </p>
                    <?php endif; ?>
                    <?php if ($p['admin_note']): ?>
                    <p style="font-size:11px;color:#c42b1c;margin-top:2px;">
                        Note: <?= htmlspecialchars(mb_substr($p['admin_note'], 0, 50)) ?>
                    </p>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text-secondary);">
                    <?= htmlspecialchars($p['department'] ?? '—') ?><br>
                    <span style="color:var(--text-tertiary);"><?= htmlspecialchars($p['semester'] ?? '—') ?></span>
                </td>
                <td style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($p['shift'] ?? '—') ?></td>
                <td style="text-align:center;">
                    <?php if ($p['file_path']): ?>
                    <a href="<?= UPLOAD_URL . htmlspecialchars($p['file_path']) ?>" target="_blank"
                       class="fluent-badge" style="text-decoration:none;"><?= $ext ?> ↓</a>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text-tertiary);">
                    <?= shamsiDate($p['created_at']) ?>
                </td>
                <td style="text-align:center;">
                    <span style="background:<?= $sm['color'] ?>;color:white;font-size:10px;font-weight:700;
                                 padding:2px 10px;border-radius:10px;">
                        <?= $sm['label'] ?>
                    </span>
                </td>
                <td>
                    <button onclick="openReview(<?= $p['id'] ?>, '<?= $p['status'] ?>', <?= json_encode($p['admin_note'] ?? '') ?>)"
                            class="fluent-btn" style="padding:3px 10px;font-size:12px;">Review</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Review modal -->
<div id="reviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;
     display:none;align-items:center;justify-content:center;">
    <div class="fluent-card" style="width:420px;padding:24px;position:relative;">
        <h3 class="fluent-h2 mb-4">Review Project</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="update_id" id="reviewId">

            <div>
                <label class="fluent-label block mb-1.5">Status</label>
                <div class="fluent-input">
                    <select name="status" id="reviewStatus">
                        <option value="submitted">Submitted</option>
                        <option value="reviewed">Reviewed ✓</option>
                        <option value="returned">Returned ✗</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Note to student
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">— optional</span>
                </label>
                <div class="fluent-input" style="padding:0;">
                    <textarea name="admin_note" id="reviewNote" rows="3"
                              style="width:100%;padding:8px 12px;background:transparent;border:none;resize:vertical;outline:none;font-size:13px;"
                              placeholder="Feedback, revision notes…"></textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit" class="fluent-btn-accent fluent-btn">Save</button>
                <button type="button" onclick="closeReview()" class="fluent-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
var modal = document.getElementById('reviewModal');
function openReview(id, status, note) {
    document.getElementById('reviewId').value     = id;
    document.getElementById('reviewStatus').value = status;
    document.getElementById('reviewNote').value   = note || '';
    modal.style.display = 'flex';
}
function closeReview() { modal.style.display = 'none'; }
modal.addEventListener('click', function (e) { if (e.target === modal) closeReview(); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeReview(); });

// Status pill radio behavior
document.querySelectorAll('input[type=radio][name=status]').forEach(function (r) {
    r.addEventListener('change', function () {
        document.querySelector('[type=submit][form]');
        this.closest('form').submit();
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
