<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shamsi.php';
require_role('student');

$pageTitle = 'Materials — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT department, semester, shift FROM students WHERE user_id=?');
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

$hasProfile = $student && $student['department'] && $student['semester'] && $student['shift'];

// ── Filters ───────────────────────────────────────────────────────────────────
$fSem     = trim($_GET['semester']  ?? '');
$fSubject = trim($_GET['subject']   ?? '');
$fDue     = trim($_GET['due']       ?? ''); // 'upcoming' | 'overdue' | ''

$materials  = [];
$subjects   = [];

if ($hasProfile) {
    // Build WHERE
    $where  = ['(m.course_id IS NULL OR (tc.department = ? AND tc.shift = ?))'];
    $params = [$student['department'], $student['shift']];

    if ($fSem) {
        $where[]  = '(m.course_id IS NULL OR tc.semester = ?)';
        $params[] = $fSem;
    } else {
        // Default: show student's own semester + general
        $where[]  = '(m.course_id IS NULL OR tc.semester = ?)';
        $params[] = $student['semester'];
    }

    if ($fSubject) {
        $where[]  = 'tc.subject_name = ?';
        $params[] = $fSubject;
    }

    if ($fDue === 'upcoming') {
        $where[] = 'm.due_date IS NOT NULL AND m.due_date >= CURDATE()';
    } elseif ($fDue === 'overdue') {
        $where[] = 'm.due_date IS NOT NULL AND m.due_date < CURDATE()';
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.title, m.description, m.file_path, m.created_at, m.due_date,
                u.name AS teacher_name, tc.subject_name, tc.semester AS course_semester
         FROM materials m
         LEFT JOIN teachers t ON t.id = m.teacher_id
         LEFT JOIN users u ON u.id = t.user_id
         LEFT JOIN teacher_courses tc ON tc.id = m.course_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY m.created_at DESC'
    );
    $stmt->execute($params);
    $materials = $stmt->fetchAll();

    // Available subjects for filter dropdown
    $st2 = $pdo->prepare(
        'SELECT DISTINCT tc.subject_name
         FROM materials m
         JOIN teacher_courses tc ON tc.id = m.course_id
         WHERE tc.department = ? AND tc.semester = ? AND tc.shift = ?
         ORDER BY tc.subject_name'
    );
    $st2->execute([$student['department'], $student['semester'], $student['shift']]);
    $subjects = $st2->fetchAll(PDO::FETCH_COLUMN);
}

$semOptions = [];
for ($i = 1; $i <= 6; $i++) {
    $suf = $i <= 3 ? ['','st','nd','rd'][$i] : 'th';
    $semOptions[] = $i . $suf . ' Semester';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.mat-card { display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border-bottom:1px solid var(--border); }
.mat-card:last-child { border-bottom:none; }
.mat-icon { width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;
            background:color-mix(in srgb,var(--accent) 10%,transparent);flex-shrink:0; }
.due-badge { display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;
             padding:2px 8px;border-radius:10px; }
</style>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Study Materials</h1>
            <p class="fluent-caption mt-1">Materials uploaded by your teachers.</p>
        </div>
    </div>

    <?php if (!$hasProfile): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <p class="fluent-body" style="color:var(--text-tertiary);">Your class information is not set up yet. Please contact an administrator.</p>
    </div>
    <?php else: ?>

    <!-- Filter bar -->
    <form method="GET" class="fluent-card p-4 mb-5 fluent-fade-in" style="animation-delay:30ms;">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">

            <!-- Semester -->
            <div>
                <label class="fluent-label block mb-1">Semester</label>
                <div class="fluent-input">
                    <select name="semester">
                        <option value="<?= htmlspecialchars($student['semester']) ?>">
                            My Semester (<?= htmlspecialchars($student['semester']) ?>)
                        </option>
                        <?php foreach ($semOptions as $s): if ($s === $student['semester']) continue; ?>
                        <option <?= $fSem === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Subject -->
            <div>
                <label class="fluent-label block mb-1">Subject</label>
                <div class="fluent-input">
                    <select name="subject">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $sub): ?>
                        <option <?= $fSubject === $sub ? 'selected' : '' ?>><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Due date filter -->
            <div>
                <label class="fluent-label block mb-1">Closing Date</label>
                <div class="fluent-input">
                    <select name="due">
                        <option value="">All Materials</option>
                        <option value="upcoming" <?= $fDue === 'upcoming' ? 'selected' : '' ?>>Upcoming deadline</option>
                        <option value="overdue"  <?= $fDue === 'overdue'  ? 'selected' : '' ?>>Past deadline</option>
                    </select>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                <button type="submit" class="fluent-btn-accent fluent-btn flex-1" style="font-size:13px;">Filter</button>
                <?php if ($fSubject || $fDue || ($fSem && $fSem !== $student['semester'])): ?>
                <a href="materials.php" class="fluent-btn" style="font-size:13px;">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (empty($materials)): ?>
    <div class="fluent-card p-12 text-center fluent-fade-in">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No materials found for the selected filters.</p>
        <?php if ($fSubject || $fDue): ?>
        <a href="materials.php" style="font-size:13px;color:var(--accent);">Clear filters</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="fluent-card fluent-fade-in" style="animation-delay:60ms;">
        <?php foreach ($materials as $m):
            $ext      = $m['file_path'] ? strtolower(pathinfo($m['file_path'], PATHINFO_EXTENSION)) : '';
            $hasDue   = !empty($m['due_date']);
            $isOverdue = $hasDue && strtotime($m['due_date']) < strtotime('today');
            $isDueSoon = $hasDue && !$isOverdue && strtotime($m['due_date']) <= strtotime('+3 days');
        ?>
        <div class="mat-card">
            <div class="mat-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p style="font-weight:600;font-size:14px;"><?= htmlspecialchars($m['title']) ?></p>
                <?php if ($m['description']): ?>
                <p style="font-size:12px;color:var(--text-secondary);margin-top:2px;"><?= htmlspecialchars($m['description']) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-3 mt-2 flex-wrap">
                    <?php if ($m['teacher_name']): ?>
                    <span style="font-size:11px;color:var(--text-tertiary);"><?= htmlspecialchars($m['teacher_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($m['subject_name']): ?>
                    <span class="fluent-badge" style="font-size:10px;"><?= htmlspecialchars($m['subject_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($m['course_semester']): ?>
                    <span class="fluent-badge" style="font-size:10px;background:color-mix(in srgb,#7a3db3 12%,transparent);color:#7a3db3;">
                        <?= htmlspecialchars($m['course_semester']) ?>
                    </span>
                    <?php endif; ?>
                    <span style="font-size:11px;color:var(--text-tertiary);"><?= shamsiDate($m['created_at']) ?></span>

                    <!-- Closing date badge -->
                    <?php if ($hasDue): ?>
                    <span class="due-badge"
                          style="background:<?= $isOverdue ? 'color-mix(in srgb,#c42b1c 10%,transparent)' : ($isDueSoon ? 'color-mix(in srgb,#d6740b 10%,transparent)' : 'color-mix(in srgb,#107c10 10%,transparent)') ?>;
                                 color:<?= $isOverdue ? '#c42b1c' : ($isDueSoon ? '#d6740b' : '#107c10') ?>;">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <?= $isOverdue ? 'Closed ' : 'Due ' ?><?= shamsiDate($m['due_date'] . ' 00:00:00') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($m['file_path']): ?>
            <a href="<?= UPLOAD_URL . htmlspecialchars($m['file_path']) ?>" target="_blank"
               class="fluent-btn fluent-btn-accent" style="padding:6px 14px;font-size:12px;white-space:nowrap;flex-shrink:0;">
                Download <?= strtoupper($ext) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
