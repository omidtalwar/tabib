<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/departments.php';
require_role('admin');

$pageTitle = 'Teachers — ' . SITE_NAME;

$q      = trim($_GET['q']    ?? '');
$deptF  = trim($_GET['dept'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like     = '%' . $q . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($deptF !== '') {
    $where[]  = 'FIND_IN_SET(?, t.department)';
    $params[] = $deptF;
}
$wClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users u JOIN teachers t ON t.user_id=u.id $wClause"
);
$cStmt->execute($params);
$total  = (int)$cStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

// teacher_no column exists only after migrate_exam_schedule.sql has been run
$hasTeacherNo = false;
try {
    $pdo->query('SELECT teacher_no FROM teachers LIMIT 0');
    $hasTeacherNo = true;
} catch (Exception $e) {}

$teacherNoCol = $hasTeacherNo ? ', t.teacher_no' : ", '' AS teacher_no";

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.phone, u.status,
            t.qualification, t.joining_date, t.department $teacherNoCol
     FROM users u JOIN teachers t ON t.user_id=u.id
     $wClause
     ORDER BY u.name
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// Departments for filter dropdown
$allDepts = [];
try {
    $allDepts = $pdo->query('SELECT name_en FROM departments ORDER BY sort_order, name_en')
                    ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { /* migration not run yet */ }

function pageUrl(int $pg, string $q, string $dept): string {
    return '?' . http_build_query(array_filter(['q'=>$q,'dept'=>$dept,'page'=>$pg]));
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Teachers</h1>
            <p class="fluent-caption mt-1">
                <?= $total ?> teacher<?= $total !== 1 ? 's' : '' ?>
                <?= $q || $deptF ? ' — filtered' : '' ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="import.php" class="fluent-btn"
               style="color:var(--accent);border-color:color-mix(in srgb,var(--accent) 35%,transparent);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Import from Excel
            </a>
            <a href="add.php" class="fluent-btn-accent fluent-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Teacher
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="fluent-alert fluent-alert-success" data-flash>Teacher deleted successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'self'): ?>
    <div class="fluent-alert fluent-alert-danger" data-flash>You cannot delete your own account.</div>
    <?php endif; ?>

    <!-- Search + Filter -->
    <form method="get" class="fluent-card px-4 py-3 mb-4 fluent-fade-in" style="animation-delay:40ms;">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="fluent-input flex-1" style="min-width:200px;">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Search name, email, phone…" style="width:100%;">
            </div>
            <?php if (!empty($allDepts)): ?>
            <div class="fluent-input" style="min-width:180px;">
                <select name="dept" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($allDepts as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $deptF === $d ? 'selected' : '' ?>>
                        <?= dept_label($pdo, $d) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="fluent-btn">Search</button>
            <?php if ($q || $deptF): ?><a href="?" class="fluent-btn">Clear</a><?php endif; ?>
        </div>
    </form>

    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($teachers)): ?>
            <tr>
                <td colspan="6" style="text-align:center;padding:40px;color:var(--text-tertiary);">
                    No teachers found.
                    <?php if (!$q && !$deptF): ?>
                    <a href="add.php" style="color:var(--accent);">Add the first one →</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($teachers as $t): ?>
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="fluent-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0;">
                            <?= strtoupper(substr($t['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></p>
                            <?php if ($t['teacher_no']): ?>
                            <p style="font-size:11px;color:var(--accent);font-family:monospace;"><?= htmlspecialchars($t['teacher_no']) ?></p>
                            <?php elseif ($t['qualification']): ?>
                            <p style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($t['qualification']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['email']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['phone'] ?? '—') ?></td>
                <td>
                    <?php
                    $depts = array_filter(array_map('trim', explode(',', $t['department'] ?? '')));
                    foreach ($depts as $d): ?>
                    <span class="fluent-badge" style="margin-right:2px;"><?= dept_label($pdo, $d) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($depts)): ?>
                    <span style="color:var(--text-tertiary);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="fluent-badge <?= $t['status'] ? 'fluent-badge-success' : 'fluent-badge-danger' ?>">
                        <?= $t['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="edit.php?id=<?= $t['id'] ?>" class="fluent-btn"
                           style="padding:4px 12px;font-size:12px;">Edit</a>
                        <a href="courses.php?id=<?= $t['id'] ?>" class="fluent-btn"
                           style="padding:4px 12px;font-size:12px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 30%,transparent);">
                           Courses
                        </a>
                        <form method="POST" action="delete.php"
                              onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($t['name'])) ?>?')">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="fluent-btn"
                                    style="padding:4px 12px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                                Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center gap-2 mt-4 fluent-fade-in" style="animation-delay:80ms;">
        <?php if ($page > 1): ?>
        <a href="<?= pageUrl($page - 1, $q, $deptF) ?>" class="fluent-btn" style="padding:4px 12px;font-size:13px;">← Prev</a>
        <?php endif; ?>

        <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
        <a href="<?= pageUrl($p, $q, $deptF) ?>"
           class="fluent-btn"
           style="padding:4px 12px;font-size:13px;min-width:36px;text-align:center;<?= $p === $page ? 'background:var(--accent);color:#fff;border-color:var(--accent);' : '' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>

        <?php if ($page < $pages): ?>
        <a href="<?= pageUrl($page + 1, $q, $deptF) ?>" class="fluent-btn" style="padding:4px 12px;font-size:13px;">Next →</a>
        <?php endif; ?>

        <span class="fluent-caption" style="margin-left:8px;">
            <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?>
        </span>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
