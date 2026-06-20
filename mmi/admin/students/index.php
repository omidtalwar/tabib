<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Students — ' . SITE_NAME;

require_once __DIR__ . '/../../includes/departments.php';
$departments = dept_names_en($pdo);
$semBase     = ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
$semExtra    = ['5th Semester','6th Semester'];
$shifts      = ['06:00 – 09:00','09:00 – 12:00','01:00 – 04:00'];

/* ── Active filters from GET ───────────────────────────────── */
$fDept   = trim($_GET['department'] ?? '');
$fSem    = trim($_GET['semester']   ?? '');
$fShift  = trim($_GET['shift']      ?? '');
$fSearch = trim($_GET['search']     ?? '');

/* ── Pagination ────────────────────────────────────────────── */
$perPage  = 25;
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ── Build query with filters ──────────────────────────────── */
$where  = ['u.role = "student"', 'u.status = 1'];
$params = [];

if ($fDept)   { $where[] = 's.department = ?'; $params[] = $fDept; }
if ($fSem)    { $where[] = 's.semester = ?';   $params[] = $fSem; }
if ($fShift)  { $where[] = 's.shift = ?';      $params[] = $fShift; }
if ($fSearch) {
    $where[]  = '(u.name LIKE ? OR s.father_name LIKE ? OR s.roll_no LIKE ?)';
    $like     = '%' . $fSearch . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereClause = implode(' AND ', $where);

/* Count filtered rows */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN students s ON s.user_id = u.id WHERE $whereClause");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($filteredTotal / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "SELECT u.id, u.name, u.email, u.status,
               s.roll_no, s.father_name, s.department, s.semester, s.shift
        FROM users u
        JOIN students s ON s.user_id = u.id
        WHERE $whereClause
        ORDER BY s.department, s.semester, u.name
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

/* ── Stats for filter bar ──────────────────────────────────── */
$total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

/* ── Pagination URL helper ─────────────────────────────────── */
function pageUrl(int $p): string {
    $q = array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY);
    $q['page'] = $p;
    return 'index.php?' . http_build_query($q);
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Header -->
    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Students</h1>
            <p class="fluent-caption mt-1">
                <?php
                    $from = $filteredTotal ? $offset + 1 : 0;
                    $to   = min($offset + $perPage, $filteredTotal);
                    echo $from . '–' . $to . ' of ' . $filteredTotal;
                    if ($filteredTotal !== $total) echo " (filtered from $total total)";
                ?>
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
                Register Student
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="fluent-alert fluent-alert-success" data-flash>Student deleted successfully.</div>
    <?php endif; ?>

    <!-- ── Filter bar ──────────────────────────────────────── -->
    <form method="GET" class="fluent-card p-4 mb-5 fluent-fade-in" style="animation-delay:40ms;">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">

            <!-- Search -->
            <div class="md:col-span-2">
                <label class="fluent-label block mb-1">Search</label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($fSearch) ?>"
                           placeholder="Name, father name, roll no…">
                </div>
            </div>

            <!-- Department -->
            <div>
                <label class="fluent-label block mb-1">Department</label>
                <div class="fluent-input">
                    <select name="department" id="filterDept">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $fDept === $d ? 'selected' : '' ?>><?= dept_label($pdo, $d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Semester -->
            <div>
                <label class="fluent-label block mb-1">Semester</label>
                <div class="fluent-input">
                    <select name="semester" id="filterSem">
                        <option value="">All Semesters</option>
                        <?php
                        $allSems = array_merge($semBase, $semExtra);
                        foreach ($allSems as $s):
                        ?>
                        <option <?= $fSem === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Shift -->
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

        <input type="hidden" name="page" value="1">
        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                Apply Filters
            </button>
            <?php if ($fDept || $fSem || $fShift || $fSearch): ?>
            <a href="index.php" class="fluent-btn" style="font-size:13px;">
                Clear
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ── Student table ───────────────────────────────────── -->
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:80ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Roll No</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
            <tr>
                <td colspan="8" style="text-align:center;padding:48px 16px;color:var(--text-tertiary);">
                    <?php if ($fDept || $fSem || $fShift || $fSearch): ?>
                        No students match your filters.
                        <a href="index.php" style="color:var(--accent);">Clear filters</a>
                    <?php else: ?>
                        No students registered yet.
                        <a href="add.php" style="color:var(--accent);">Register the first one →</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($students as $s): ?>
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="fluent-avatar" style="width:30px;height:30px;font-size:11px;flex-shrink:0;">
                            <?= strtoupper(substr($s['name'], 0, 1)) ?>
                        </div>
                        <span style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></span>
                    </div>
                </td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['father_name'] ?? '—') ?></td>
                <td>
                    <?php if ($s['roll_no']): ?>
                    <span class="fluent-badge"><?= htmlspecialchars($s['roll_no']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);">—</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-secondary);"><?= dept_label($pdo, $s['department']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['semester'] ?? '—') ?></td>
                <td>
                    <?php if ($s['shift']): ?>
                    <span class="fluent-badge" style="background:color-mix(in srgb,#7a3db3 10%,transparent);color:#7a3db3;">
                        <?= htmlspecialchars($s['shift']) ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <span class="fluent-badge <?= $s['status'] ? 'fluent-badge-success' : 'fluent-badge-danger' ?>">
                        <?= $s['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="edit.php?id=<?= $s['id'] ?>"
                           class="fluent-btn" style="padding:3px 10px;font-size:12px;">Edit</a>
                        <form method="POST" action="delete.php"
                              onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($s['name'])) ?>?')">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="fluent-btn"
                                    style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
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

    <!-- ── Pagination ─────────────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-4 fluent-fade-in" style="animation-delay:100ms;">
        <p style="font-size:13px;color:var(--text-tertiary);">
            Page <?= $page ?> of <?= $totalPages ?>
        </p>
        <div class="flex items-center gap-1">

            <!-- Prev -->
            <?php if ($page > 1): ?>
            <a href="<?= pageUrl($page - 1) ?>" class="fluent-btn" style="padding:4px 10px;font-size:13px;">
                &lsaquo; Prev
            </a>
            <?php endif; ?>

            <?php
            // Show at most 7 page buttons with ellipsis
            $start = max(1, $page - 3);
            $end   = min($totalPages, $page + 3);
            if ($start > 1): ?>
            <a href="<?= pageUrl(1) ?>" class="fluent-btn" style="padding:4px 10px;font-size:13px;">1</a>
            <?php if ($start > 2): ?>
            <span style="font-size:13px;color:var(--text-tertiary);padding:0 4px;">…</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= pageUrl($p) ?>"
               class="fluent-btn <?= $p === $page ? 'fluent-btn-accent' : '' ?>"
               style="padding:4px 10px;font-size:13px;<?= $p === $page ? 'pointer-events:none;' : '' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <span style="font-size:13px;color:var(--text-tertiary);padding:0 4px;">…</span>
            <?php endif; ?>
            <a href="<?= pageUrl($totalPages) ?>" class="fluent-btn" style="padding:4px 10px;font-size:13px;"><?= $totalPages ?></a>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
            <a href="<?= pageUrl($page + 1) ?>" class="fluent-btn" style="padding:4px 10px;font-size:13px;">
                Next &rsaquo;
            </a>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
