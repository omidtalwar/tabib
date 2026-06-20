<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Exam Center — ' . SITE_NAME;
$tab = $_GET['tab'] ?? 'submissions';
if (!in_array($tab, ['submissions', 'results', 'exam_schedule'])) $tab = 'submissions';

// ── Tab 1: Submissions data ───────────────────────────────────────────────────
$rows = [];
$filterStatus = 'submitted';
$searchQ = '';
if ($tab === 'submissions') {
    $filterStatus = $_GET['status'] ?? 'submitted';
    if (!in_array($filterStatus, ['submitted','approved','draft','all'])) $filterStatus = 'submitted';
    $searchQ = trim($_GET['q'] ?? '');

    $where  = 'WHERE 1';
    $params = [];
    if ($filterStatus !== 'all')  { $where .= ' AND es.status = ?'; $params[] = $filterStatus; }
    if ($searchQ) {
        $where .= ' AND (u.name LIKE ? OR tc.subject_name LIKE ?)';
        $like   = '%' . $searchQ . '%';
        $params[] = $like; $params[] = $like;
    }

    $stmt = $pdo->prepare(
        'SELECT es.id, es.exam_type, es.status, es.submitted_at, es.approved_at,
                tc.subject_name, tc.department, tc.semester, tc.shift, tc.credits,
                u.name AS teacher_name,
                approver.name AS approver_name,
                (SELECT COUNT(*) FROM exam_scores sc
                 WHERE sc.teacher_course_id = es.teacher_course_id
                   AND sc.exam_type = es.exam_type
                   AND sc.score IS NOT NULL) AS scored_count
         FROM exam_submissions es
         JOIN teacher_courses tc  ON tc.id  = es.teacher_course_id
         JOIN teachers t          ON t.id   = tc.teacher_id
         JOIN users u             ON u.id   = t.user_id
         LEFT JOIN users approver ON approver.id = es.approved_by
         ' . $where . '
         ORDER BY FIELD(es.status,"submitted","draft","approved"), es.submitted_at DESC'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

// Submission counts for tab badges
$cntSubmitted = (int)$pdo->query('SELECT COUNT(*) FROM exam_submissions WHERE status="submitted"')->fetchColumn();
$cntApproved  = (int)$pdo->query('SELECT COUNT(*) FROM exam_submissions WHERE status="approved"')->fetchColumn();
$cntDraft     = (int)$pdo->query('SELECT COUNT(*) FROM exam_submissions WHERE status="draft"')->fetchColumn();

// ── Tab 2: Class Results data ─────────────────────────────────────────────────
$courses      = [];
$studentCount = 0;
$departments  = $semesters = $shifts = [];
$filterDept   = trim($_GET['dept']  ?? '');
$filterSem    = trim($_GET['sem']   ?? '');
$filterShift  = trim($_GET['shift'] ?? '');
$classSelected = $filterDept !== '' && $filterSem !== '' && $filterShift !== '';

if ($tab === 'results') {
    $departments = $pdo->query(
        'SELECT DISTINCT department FROM teacher_courses WHERE department IS NOT NULL AND department!="" ORDER BY department'
    )->fetchAll(PDO::FETCH_COLUMN);
    $semesters = $pdo->query(
        'SELECT DISTINCT semester FROM teacher_courses WHERE semester IS NOT NULL AND semester!="" ORDER BY semester'
    )->fetchAll(PDO::FETCH_COLUMN);
    $shifts = $pdo->query(
        'SELECT DISTINCT shift FROM teacher_courses WHERE shift IS NOT NULL AND shift!="" ORDER BY shift'
    )->fetchAll(PDO::FETCH_COLUMN);

    if ($classSelected) {
        $stmt = $pdo->prepare(
            'SELECT tc.*, u.name AS teacher_name,
                    sub_mid.id     AS mid_sub_id,  sub_mid.status  AS mid_status,
                    sub_fin.id     AS fin_sub_id,  sub_fin.status  AS fin_status,
                    (SELECT COUNT(*) FROM exam_scores sc
                     WHERE sc.teacher_course_id=tc.id AND sc.exam_type="midterm"
                       AND sc.score IS NOT NULL) AS mid_scored,
                    (SELECT COUNT(*) FROM exam_scores sc
                     WHERE sc.teacher_course_id=tc.id AND sc.exam_type="final"
                       AND sc.score IS NOT NULL) AS fin_scored
             FROM teacher_courses tc
             JOIN teachers t  ON t.id  = tc.teacher_id
             JOIN users u     ON u.id  = t.user_id
             LEFT JOIN exam_submissions sub_mid
                    ON sub_mid.teacher_course_id = tc.id AND sub_mid.exam_type = "midterm"
             LEFT JOIN exam_submissions sub_fin
                    ON sub_fin.teacher_course_id = tc.id AND sub_fin.exam_type = "final"
             WHERE tc.department = ? AND tc.semester = ? AND tc.shift = ?
             ORDER BY tc.no, tc.id'
        );
        $stmt->execute([$filterDept, $filterSem, $filterShift]);
        $courses = $stmt->fetchAll();

        $sc = $pdo->prepare('SELECT COUNT(*) FROM students WHERE department=? AND semester=? AND shift=?');
        $sc->execute([$filterDept, $filterSem, $filterShift]);
        $studentCount = (int)$sc->fetchColumn();
    }
}

// ── Tab 3: Exam Schedule data ─────────────────────────────────────────────────
$examSchedules  = [];
$esFilterDept   = trim($_GET['es_dept']  ?? '');
$esFilterSem    = trim($_GET['es_sem']   ?? '');
$esFilterShift  = trim($_GET['es_shift'] ?? '');
$esFilterType   = trim($_GET['es_type']  ?? '');

if ($tab === 'exam_schedule') {
    $esWhere  = ['1'];
    $esParams = [];
    if ($esFilterDept)  { $esWhere[] = 'es.department = ?'; $esParams[] = $esFilterDept; }
    if ($esFilterSem)   { $esWhere[] = 'es.semester = ?';   $esParams[] = $esFilterSem; }
    if ($esFilterShift) { $esWhere[] = 'es.shift = ?';      $esParams[] = $esFilterShift; }
    if ($esFilterType)  { $esWhere[] = 'es.exam_type = ?';  $esParams[] = $esFilterType; }

    $esStmt = $pdo->prepare(
        'SELECT es.*,
                u1.name AS inv1_name,
                u2.name AS inv2_name
         FROM exam_schedules es
         LEFT JOIN teachers t1 ON t1.id = es.invigilator_id
         LEFT JOIN users    u1 ON u1.id = t1.user_id
         LEFT JOIN teachers t2 ON t2.id = es.invigilator2_id
         LEFT JOIN users    u2 ON u2.id = t2.user_id
         WHERE ' . implode(' AND ', $esWhere) . '
         ORDER BY es.exam_date ASC, es.start_time ASC'
    );
    $esStmt->execute($esParams);
    $examSchedules = $esStmt->fetchAll();
}

// Teachers list for invigilator selects (always loaded — used in modal)
$allTeachers = [];
try {
    $allTeachers = $pdo->query(
        'SELECT t.id, u.name, t.teacher_no FROM teachers t JOIN users u ON u.id = t.user_id
         WHERE u.status = 1 ORDER BY u.name'
    )->fetchAll();
} catch (Exception $e) {}

$esDepts  = [];
$esSems   = [];
$esShifts = [];
try {
    $esDepts  = $pdo->query('SELECT DISTINCT department FROM exam_schedules WHERE department IS NOT NULL ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);
    $esSems   = $pdo->query('SELECT DISTINCT semester FROM exam_schedules WHERE semester IS NOT NULL ORDER BY semester')->fetchAll(PDO::FETCH_COLUMN);
    $esShifts = $pdo->query('SELECT DISTINCT shift FROM exam_schedules WHERE shift IS NOT NULL ORDER BY shift')->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$cntExamSchedules = 0;
try {
    $cntExamSchedules = (int)$pdo->query('SELECT COUNT(*) FROM exam_schedules WHERE exam_date >= CURDATE()')->fetchColumn();
} catch (Exception $e) {}

// Flash messages
$flashApproved = $_GET['approved'] ?? null;
$flashReopen   = $_GET['reopened'] ?? null;

// ── Helper: status badge HTML ──────────────────────────────────────────────────
function statusBadge(?string $status, ?int $count = null): string {
    if (!$status || $status === 'none') {
        return '<span class="fluent-badge" style="color:var(--text-tertiary);border-color:transparent;background:transparent;">—</span>';
    }
    $label = ['draft' => 'Draft', 'submitted' => 'Pending', 'approved' => 'Approved'][$status] ?? $status;
    $style = match($status) {
        'submitted' => 'background:rgba(15,108,189,.12);color:#0f6cbd;border:1px solid rgba(15,108,189,.3);',
        'approved'  => 'background:rgba(14,122,14,.12);color:#0e7a0e;border:1px solid rgba(14,122,14,.3);',
        'draft'     => 'background:var(--surface-hover);color:var(--text-secondary);',
        default     => '',
    };
    $cnt = $count !== null ? ' <span style="opacity:.65;">(' . $count . ')</span>' : '';
    return '<span class="fluent-badge" style="' . $style . '">' . $label . $cnt . '</span>';
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

<div class="mb-5 fluent-fade-in">
    <h1 class="fluent-h1">Exam Center</h1>
    <p class="fluent-caption mt-1">Approve teacher submissions and download class scoresheets.</p>
</div>

<!-- Flash alerts -->
<?php if ($flashApproved): ?>
<div class="fluent-alert mb-4" style="background:rgba(14,122,14,.08);border-color:#0e7a0e;color:#0e7a0e;" data-flash>
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    Scores approved successfully.
</div>
<?php endif; ?>
<?php if ($flashReopen): ?>
<div class="fluent-alert mb-4" style="background:rgba(232,162,0,.08);border-color:rgba(232,162,0,.5);color:#b07800;" data-flash>
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
    </svg>
    Submission reopened — teacher can now edit and resubmit.
</div>
<?php endif; ?>

<!-- ── Tab Navigation ─────────────────────────────────────────────────────── -->
<div class="flex gap-1 mb-5 fluent-fade-in" style="border-bottom:2px solid var(--border);padding-bottom:0;">
    <?php
    $tabDefs = [
        'submissions'   => ['Submissions', $cntSubmitted ? ' (' . $cntSubmitted . ' pending)' : ''],
        'results'       => ['Class Results', ''],
        'exam_schedule' => ['Exam Schedule', $cntExamSchedules ? ' (' . $cntExamSchedules . ' upcoming)' : ''],
    ];
    foreach ($tabDefs as $key => [$label, $hint]):
        $isActive = $tab === $key;
    ?>
    <a href="?tab=<?= $key ?>"
       style="padding:8px 18px;font-size:13px;font-weight:600;text-decoration:none;
              border-bottom:2px solid <?= $isActive ? 'var(--accent)' : 'transparent' ?>;
              margin-bottom:-2px;
              color:<?= $isActive ? 'var(--accent)' : 'var(--text-secondary)' ?>;
              transition:color .15s;">
        <?= $label ?><?php if ($hint): ?><span style="font-weight:400;font-size:12px;opacity:.7;"><?= $hint ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'submissions'): ?>
<!-- ══════════════════════════════════════════════════════════════════════
     TAB 1 — SUBMISSIONS
     ══════════════════════════════════════════════════════════════════════ -->

<!-- Stats row -->
<div class="grid grid-cols-3 gap-3 mb-5 fluent-fade-in" style="animation-delay:30ms;">
    <?php foreach ([
        ['Pending Approval', $cntSubmitted, '#0f6cbd'],
        ['Approved',         $cntApproved,  '#0e7a0e'],
        ['Drafts',           $cntDraft,     '#8a6f00'],
    ] as [$lbl, $val, $col]): ?>
    <div class="fluent-card px-4 py-3 flex items-center gap-3">
        <div style="width:8px;height:36px;border-radius:4px;background:<?= $col ?>;flex-shrink:0;"></div>
        <div>
            <p class="fluent-label"><?= $lbl ?></p>
            <p style="font-size:22px;font-weight:700;color:<?= $col ?>;line-height:1.1;"><?= $val ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters row -->
<div class="flex flex-wrap gap-3 items-center mb-4 fluent-fade-in" style="animation-delay:40ms;">
    <!-- Status tabs -->
    <div class="flex gap-1">
        <?php $statusTabs = ['submitted'=>'Pending','approved'=>'Approved','draft'=>'Drafts','all'=>'All'];
        foreach ($statusTabs as $val => $label):
            $active = $filterStatus === $val;
        ?>
        <a href="?tab=submissions&status=<?= $val ?><?= $searchQ ? '&q='.urlencode($searchQ) : '' ?>"
           style="<?= $active
               ? 'background:var(--accent);color:#fff;border-color:var(--accent);'
               : 'color:var(--text-secondary);background:transparent;border-color:var(--border);' ?>
               padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;
               border:1px solid;text-decoration:none;">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
    <!-- Search -->
    <form method="GET" style="display:flex;align-items:center;gap:6px;margin-left:auto;">
        <input type="hidden" name="tab" value="submissions">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        <div class="fluent-input" style="width:220px;">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="q" placeholder="Search teacher or subject…"
                   value="<?= htmlspecialchars($searchQ) ?>">
        </div>
        <?php if ($searchQ): ?>
        <a href="?tab=submissions&status=<?= $filterStatus ?>" class="fluent-btn" style="padding:7px 10px;">✕</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($rows)): ?>
<div class="fluent-card p-12 text-center fluent-fade-in" style="animation-delay:60ms;">
    <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <p style="color:var(--text-tertiary);"><?= $searchQ ? 'No matches for "' . htmlspecialchars($searchQ) . '".' : 'No submissions here.' ?></p>
</div>
<?php else: ?>
<div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
    <table class="fluent-table">
        <thead>
            <tr>
                <th>Teacher</th>
                <th>Subject</th>
                <th>Class</th>
                <th>Exam</th>
                <th>Scored</th>
                <th>Submitted</th>
                <th style="width:130px;">Status</th>
                <th style="width:160px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($r['teacher_name']) ?></td>
            <td><?= htmlspecialchars($r['subject_name']) ?></td>
            <td style="font-size:12px;color:var(--text-secondary);">
                <?= htmlspecialchars($r['department'] ?? '—') ?><br>
                <?= htmlspecialchars($r['semester'] ?? '—') ?> · <?= htmlspecialchars($r['shift'] ?? '—') ?>
            </td>
            <td>
                <span class="fluent-badge <?= $r['exam_type']==='midterm' ? 'fluent-badge-warning' : '' ?>"
                      style="text-transform:capitalize;"><?= $r['exam_type'] ?></span>
            </td>
            <td style="font-size:13px;color:var(--text-secondary);">
                <?= (int)$r['scored_count'] ?> student<?= $r['scored_count']!=1?'s':'' ?>
            </td>
            <td style="font-size:12px;color:var(--text-tertiary);">
                <?= $r['submitted_at'] ? date('d M Y', strtotime($r['submitted_at'])) : '—' ?>
            </td>
            <td>
                <?= statusBadge($r['status']) ?>
                <?php if ($r['status']==='approved' && $r['approver_name']): ?>
                <div style="font-size:10px;color:var(--text-tertiary);margin-top:2px;">by <?= htmlspecialchars($r['approver_name']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="flex gap-1 flex-wrap items-center">
                    <a href="view.php?id=<?= (int)$r['id'] ?>" class="fluent-btn" style="padding:3px 9px;font-size:11px;">View</a>
                    <?php if ($r['status']==='submitted'): ?>
                    <form method="POST" action="approve.php" onsubmit="return confirm('Approve these scores?')">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="fluent-btn-accent fluent-btn" style="padding:3px 9px;font-size:11px;">Approve</button>
                    </form>
                    <?php endif; ?>
                    <?php if (in_array($r['status'], ['submitted','approved'])): ?>
                    <form method="POST" action="reopen.php"
                          onsubmit="return confirm('Reopen this submission so the teacher can edit?')">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="fluent-btn"
                                style="padding:3px 9px;font-size:11px;color:#e8a200;border-color:rgba(232,162,0,.4);">
                            Reopen
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════
     TAB 2 — CLASS RESULTS
     ══════════════════════════════════════════════════════════════════════ -->

<!-- Filter bar -->
<form method="GET" class="fluent-card px-5 py-4 mb-5 fluent-fade-in" style="animation-delay:30ms;">
    <input type="hidden" name="tab" value="results">
    <div class="flex flex-wrap gap-3 items-end">

        <div class="flex-1" style="min-width:180px;">
            <label class="fluent-label block mb-1">Department</label>
            <div class="fluent-input">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <select name="dept" onchange="this.form.submit()">
                    <option value="">— All Departments —</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex-1" style="min-width:150px;">
            <label class="fluent-label block mb-1">Semester</label>
            <div class="fluent-input">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <select name="sem" onchange="this.form.submit()">
                    <option value="">— All Semesters —</option>
                    <?php foreach ($semesters as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filterSem===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex-1" style="min-width:140px;">
            <label class="fluent-label block mb-1">Shift</label>
            <div class="fluent-input">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <select name="shift" onchange="this.form.submit()">
                    <option value="">— All Shifts —</option>
                    <?php foreach ($shifts as $sh): ?>
                    <option value="<?= htmlspecialchars($sh) ?>" <?= $filterShift===$sh?'selected':'' ?>><?= htmlspecialchars($sh) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($filterDept || $filterSem || $filterShift): ?>
        <a href="?tab=results" class="fluent-btn" style="font-size:13px;white-space:nowrap;">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Clear
        </a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$classSelected): ?>
<div class="fluent-card p-14 text-center fluent-fade-in" style="animation-delay:60ms;">
    <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
    </svg>
    <p style="color:var(--text-secondary);">Select Department, Semester, and Shift to view class results.</p>
</div>

<?php elseif (empty($courses)): ?>
<div class="fluent-card p-12 text-center fluent-fade-in">
    <p style="color:var(--text-tertiary);">No subjects found for this class.</p>
</div>

<?php else: ?>
<!-- Info bar -->
<div class="flex items-center gap-3 mb-4 fluent-fade-in" style="animation-delay:50ms;">
    <span class="fluent-body" style="font-weight:600;">
        <?= htmlspecialchars($filterDept) ?> · <?= htmlspecialchars($filterSem) ?> · <?= htmlspecialchars($filterShift) ?>
    </span>
    <span class="fluent-badge"><?= count($courses) ?> subject<?= count($courses)!==1?'s':'' ?></span>
    <span class="fluent-badge fluent-badge-success"><?= $studentCount ?> student<?= $studentCount!==1?'s':'' ?></span>
</div>

<div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:70ms;">
    <table class="fluent-table">
        <thead>
            <tr>
                <th style="width:44px;">No.</th>
                <th>Subject</th>
                <th>Teacher</th>
                <th style="width:64px;text-align:center;">Cr.</th>
                <th style="width:130px;text-align:center;">Midterm</th>
                <th style="width:130px;text-align:center;">Final</th>
                <th style="width:210px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
        <tr>
            <td style="color:var(--text-tertiary);font-weight:600;"><?= (int)$c['no'] ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($c['subject_name']) ?></td>
            <td style="color:var(--text-secondary);font-size:13px;"><?= htmlspecialchars($c['teacher_name'] ?? '—') ?></td>
            <td style="text-align:center;">
                <span class="fluent-badge fluent-badge-success"><?= (int)$c['credits'] ?></span>
            </td>
            <td style="text-align:center;">
                <?= statusBadge($c['mid_status'] ?? 'none', (int)$c['mid_scored'] ?: null) ?>
            </td>
            <td style="text-align:center;">
                <?= statusBadge($c['fin_status'] ?? 'none', (int)$c['fin_scored'] ?: null) ?>
            </td>
            <td>
                <div class="flex gap-1 flex-wrap">
                    <?php if ($c['mid_sub_id']): ?>
                    <a href="view.php?id=<?= (int)$c['mid_sub_id'] ?>" class="fluent-btn" style="padding:3px 8px;font-size:11px;">Mid</a>
                    <?php if ($c['mid_status']==='submitted'): ?>
                    <form method="POST" action="approve.php" onsubmit="return confirm('Approve midterm scores?')">
                        <input type="hidden" name="id" value="<?= (int)$c['mid_sub_id'] ?>">
                        <input type="hidden" name="redirect" value="?tab=results&dept=<?= urlencode($filterDept) ?>&sem=<?= urlencode($filterSem) ?>&shift=<?= urlencode($filterShift) ?>">
                        <button type="submit" class="fluent-btn-accent fluent-btn" style="padding:3px 8px;font-size:11px;">✓ Mid</button>
                    </form>
                    <?php elseif ($c['mid_status']==='approved'): ?>
                    <form method="POST" action="reopen.php" onsubmit="return confirm('Reopen midterm for editing?')">
                        <input type="hidden" name="id" value="<?= (int)$c['mid_sub_id'] ?>">
                        <button type="submit" class="fluent-btn" style="padding:3px 8px;font-size:11px;color:#e8a200;border-color:rgba(232,162,0,.4);">↺ Mid</button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($c['fin_sub_id']): ?>
                    <a href="view.php?id=<?= (int)$c['fin_sub_id'] ?>" class="fluent-btn" style="padding:3px 8px;font-size:11px;">Final</a>
                    <?php if ($c['fin_status']==='submitted'): ?>
                    <form method="POST" action="approve.php" onsubmit="return confirm('Approve final scores?')">
                        <input type="hidden" name="id" value="<?= (int)$c['fin_sub_id'] ?>">
                        <button type="submit" class="fluent-btn-accent fluent-btn" style="padding:3px 8px;font-size:11px;">✓ Final</button>
                    </form>
                    <?php elseif ($c['fin_status']==='approved'): ?>
                    <form method="POST" action="reopen.php" onsubmit="return confirm('Reopen final for editing?')">
                        <input type="hidden" name="id" value="<?= (int)$c['fin_sub_id'] ?>">
                        <button type="submit" class="fluent-btn" style="padding:3px 8px;font-size:11px;color:#e8a200;border-color:rgba(232,162,0,.4);">↺ Final</button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- Shoqa download -->
                    <button type="button"
                            class="fluent-btn open-shoqa"
                            data-id="<?= (int)$c['id'] ?>"
                            data-subject="<?= htmlspecialchars($c['subject_name'], ENT_QUOTES) ?>"
                            data-teacher="<?= htmlspecialchars($c['teacher_name'] ?? '', ENT_QUOTES) ?>"
                            style="padding:3px 8px;font-size:11px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 30%,transparent);">
                        ⬇ Shoqa
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ── Shoqa Modal ─────────────────────────────────────────────────────── -->
<div id="shoqaModal" class="fixed inset-0 z-50 items-center justify-center hidden"
     style="display:none;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);">
    <div class="fluent-card w-full max-w-sm mx-4" style="box-shadow:var(--shadow-lg);">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid var(--border);">
            <div>
                <h2 class="fluent-h3" id="shoqaSubject">Subject</h2>
                <p class="fluent-caption mt-0.5" id="shoqaTeacher"></p>
            </div>
            <button id="shoqaClose" class="w-8 h-8 rounded-md flex items-center justify-center"
                    style="color:var(--text-tertiary);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="fluent-label block mb-1.5">Exam Type</label>
                <div class="fluent-input">
                    <select id="shoqaExamType">
                        <option value="">— Select —</option>
                        <option value="midterm">Midterm (20%)</option>
                        <option value="final">Final (80%)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="fluent-label block mb-1.5">Chance</label>
                <div class="fluent-input">
                    <select id="shoqaChance">
                        <option value="first">First Chance</option>
                        <option value="second">Second Chance</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="flex gap-2 px-6 py-4" style="border-top:1px solid var(--border);">
            <button id="shoqaDownload" class="fluent-btn-accent fluent-btn" style="font-size:13px;gap:5px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Shoqa
            </button>
            <button id="shoqaCancel" class="fluent-btn" style="font-size:13px;">Cancel</button>
        </div>
    </div>
</div>
<script>
(function () {
    var activeCourseId = 0;
    var modal = document.getElementById('shoqaModal');
    function openShoqa() { modal.style.display = 'flex'; }
    function closeShoqa() { modal.style.display = 'none'; }
    document.querySelectorAll('.open-shoqa').forEach(function(btn) {
        btn.addEventListener('click', function() {
            activeCourseId = this.dataset.id;
            document.getElementById('shoqaSubject').textContent = this.dataset.subject;
            document.getElementById('shoqaTeacher').textContent = this.dataset.teacher ? 'Teacher: ' + this.dataset.teacher : '';
            document.getElementById('shoqaExamType').value = '';
            document.getElementById('shoqaChance').value  = 'first';
            openShoqa();
        });
    });
    document.getElementById('shoqaClose').addEventListener('click', closeShoqa);
    document.getElementById('shoqaCancel').addEventListener('click', closeShoqa);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeShoqa(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeShoqa(); });
    document.getElementById('shoqaDownload').addEventListener('click', function() {
        var examType = document.getElementById('shoqaExamType').value;
        var chance   = document.getElementById('shoqaChance').value;
        if (!examType) { alert('Please select an exam type.'); return; }
        window.location.href = '<?= BASE_URL ?>/teacher/download_shoqa.php'
            + '?course_id=' + encodeURIComponent(activeCourseId)
            + '&exam_type=' + encodeURIComponent(examType)
            + '&chance='    + encodeURIComponent(chance);
        closeShoqa();
    });
})();
</script>
<?php endif; ?>

<?php if ($tab === 'exam_schedule'): ?>
<!-- ══════════════════════════════════════════════════════════════════════
     TAB 3 — EXAM SCHEDULE
     ══════════════════════════════════════════════════════════════════════ -->

<?php if (isset($_GET['saved'])): ?>
<div class="fluent-alert mb-4" style="background:rgba(14,122,14,.08);border-color:#0e7a0e;color:#0e7a0e;" data-flash>
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    Exam schedule saved successfully.
</div>
<?php endif; ?>

<!-- Filter + Add button row -->
<form method="GET" class="fluent-card px-5 py-4 mb-5 fluent-fade-in" style="animation-delay:30ms;">
    <input type="hidden" name="tab" value="exam_schedule">
    <div class="flex flex-wrap gap-3 items-end">

        <div class="flex-1" style="min-width:160px;">
            <label class="fluent-label block mb-1">Department</label>
            <div class="fluent-input">
                <select name="es_dept">
                    <option value="">All</option>
                    <?php foreach ($esDepts as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $esFilterDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex-1" style="min-width:140px;">
            <label class="fluent-label block mb-1">Semester</label>
            <div class="fluent-input">
                <select name="es_sem">
                    <option value="">All</option>
                    <?php foreach ($esSems as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $esFilterSem===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex-1" style="min-width:130px;">
            <label class="fluent-label block mb-1">Shift</label>
            <div class="fluent-input">
                <select name="es_shift">
                    <option value="">All</option>
                    <?php foreach ($esShifts as $sh): ?>
                    <option value="<?= htmlspecialchars($sh) ?>" <?= $esFilterShift===$sh?'selected':'' ?>><?= htmlspecialchars($sh) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="min-width:120px;">
            <label class="fluent-label block mb-1">Exam Type</label>
            <div class="fluent-input">
                <select name="es_type">
                    <option value="">All</option>
                    <option value="midterm" <?= $esFilterType==='midterm'?'selected':'' ?>>Midterm</option>
                    <option value="final"   <?= $esFilterType==='final'?'selected':'' ?>>Final</option>
                </select>
            </div>
        </div>

        <button type="submit" class="fluent-btn" style="font-size:13px;">Filter</button>
        <?php if ($esFilterDept||$esFilterSem||$esFilterShift||$esFilterType): ?>
        <a href="?tab=exam_schedule" class="fluent-btn" style="font-size:13px;">Clear</a>
        <?php endif; ?>

        <a href="schedule_save.php" class="fluent-btn-accent fluent-btn" style="font-size:13px;margin-left:auto;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Exam
        </a>
    </div>
</form>

<!-- Schedule table -->
<?php if (empty($examSchedules)): ?>
<div class="fluent-card p-12 text-center fluent-fade-in" style="animation-delay:50ms;">
    <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    <p style="color:var(--text-tertiary);">No exam schedules found. <a href="schedule_save.php" style="color:var(--accent);">Add the first one →</a></p>
</div>
<?php else: ?>
<div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:50ms;">
    <table class="fluent-table">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Type</th>
                <th>Class</th>
                <th>Date</th>
                <th>Time</th>
                <th>Room</th>
                <th>Invigilators</th>
                <th style="width:110px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($examSchedules as $es): ?>
        <?php $isPast = strtotime($es['exam_date']) < strtotime('today'); ?>
        <tr style="<?= $isPast ? 'opacity:.55;' : '' ?>">
            <td style="font-weight:600;"><?= htmlspecialchars($es['subject_name']) ?></td>
            <td>
                <span class="fluent-badge <?= $es['exam_type']==='midterm' ? '' : 'fluent-badge-success' ?>"
                      style="text-transform:capitalize;"><?= $es['exam_type'] ?></span>
            </td>
            <td style="font-size:12px;color:var(--text-secondary);">
                <?= htmlspecialchars($es['department'] ?? '—') ?><br>
                <?= htmlspecialchars($es['semester'] ?? '') ?>
                <?php if ($es['shift']): ?> · <?= htmlspecialchars($es['shift']) ?><?php endif; ?>
            </td>
            <td style="font-weight:600;white-space:nowrap;">
                <?= date('d M Y', strtotime($es['exam_date'])) ?>
                <?php if (!$isPast && strtotime($es['exam_date']) <= strtotime('+3 days')): ?>
                <span class="fluent-badge" style="background:rgba(196,43,28,.12);color:#c42b1c;border:none;font-size:10px;">Soon</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-secondary);white-space:nowrap;">
                <?= $es['start_time'] ? substr($es['start_time'],0,5) : '—' ?>
                <?= $es['end_time']   ? ' – ' . substr($es['end_time'],0,5) : '' ?>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($es['room'] ?? '—') ?></td>
            <td style="font-size:12px;">
                <?php if ($es['inv1_name']): ?>
                <p style="color:var(--text-secondary);"><?= htmlspecialchars($es['inv1_name']) ?></p>
                <?php endif; ?>
                <?php if ($es['inv2_name']): ?>
                <p style="color:var(--text-tertiary);"><?= htmlspecialchars($es['inv2_name']) ?></p>
                <?php endif; ?>
                <?php if (!$es['inv1_name'] && !$es['inv2_name']): ?>—<?php endif; ?>
            </td>
            <td>
                <div class="flex gap-1">
                    <a href="schedule_save.php?id=<?= (int)$es['id'] ?>"
                       class="fluent-btn" style="padding:3px 9px;font-size:11px;">Edit</a>
                    <form method="POST" action="schedule_delete.php"
                          onsubmit="return confirm('Delete this exam schedule?')">
                        <input type="hidden" name="id" value="<?= (int)$es['id'] ?>">
                        <input type="hidden" name="redirect" value="?tab=exam_schedule">
                        <button type="submit" class="fluent-btn"
                                style="padding:3px 9px;font-size:11px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                            Del
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
