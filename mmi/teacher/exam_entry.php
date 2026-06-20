<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$user     = current_user();
$courseId = (int)($_GET['course_id'] ?? 0);
$examType = $_GET['exam_type'] ?? '';
$sortBy   = $_GET['sort_by']   ?? 'roll_no';
$chance   = $_GET['chance']    ?? 'first';
if (!in_array($chance, ['first', 'second'])) $chance = 'first';

if (!in_array($examType, ['midterm', 'final']) || !$courseId) {
    header('Location: exam_result.php'); exit;
}

$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();
if (!$teacher) { header('Location: exam_result.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM teacher_courses WHERE id = ? AND teacher_id = ?');
$stmt->execute([$courseId, $teacher['id']]);
$course = $stmt->fetch();
if (!$course) { header('Location: exam_result.php'); exit; }

$maxMarks  = $examType === 'midterm' ? 20 : 80;
$examLabel = $examType === 'midterm' ? 'Midterm Exam' : 'Final Exam';

function loadSubmission(PDO $pdo, int $courseId, string $examType): array|false {
    $s = $pdo->prepare('SELECT * FROM exam_submissions WHERE teacher_course_id = ? AND exam_type = ?');
    $s->execute([$courseId, $examType]);
    return $s->fetch();
}

$submission = loadSubmission($pdo, $courseId, $examType);
$subStatus  = $submission['status'] ?? null;

$orderClause = match($sortBy) {
    'name'       => 'u.name ASC',
    'marks_desc' => '(es.score IS NULL) ASC, es.score DESC, s.roll_no ASC',
    'marks_asc'  => '(es.score IS NULL) ASC, es.score ASC, s.roll_no ASC',
    default      => 's.roll_no ASC',
};

$scoreStmt = $pdo->prepare(
    'SELECT s.id AS sid, u.name, s.father_name, s.roll_no, es.score
     FROM students s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN exam_scores es
         ON es.student_id = s.id
         AND es.teacher_course_id = ?
         AND es.exam_type = ?
     WHERE s.department = ? AND s.semester = ? AND s.shift = ?
     ORDER BY ' . $orderClause
);
$scoreStmt->execute([$courseId, $examType,
    $course['department'], $course['semester'], $course['shift']]);
$students = $scoreStmt->fetchAll();

$pageTitle = 'Score Entry — ' . SITE_NAME;
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'draft';

    if ($subStatus === 'approved') {
        $error = 'These scores have been approved and cannot be changed.';
    } elseif ($action === 'recall') {
        // Pull back a submitted result to draft
        if ($subStatus === 'submitted') {
            $pdo->prepare(
                'UPDATE exam_submissions SET status="draft", submitted_at=NULL WHERE teacher_course_id=? AND exam_type=?'
            )->execute([$courseId, $examType]);
            $submission = loadSubmission($pdo, $courseId, $examType);
            $subStatus  = 'draft';
            $success    = 'Submission recalled. You can edit and resubmit.';
        }
    } else {
        // Save scores + update submission status
        $postedScores = $_POST['score'] ?? [];
        try {
            $pdo->beginTransaction();

            $upsert = $pdo->prepare(
                'INSERT INTO exam_scores (student_id, teacher_course_id, exam_type, score)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE score = VALUES(score)'
            );
            foreach ($postedScores as $sid => $rawScore) {
                $sid   = (int)$sid;
                $score = ($rawScore === '' || $rawScore === null)
                         ? null
                         : min(max(0, (float)$rawScore), $maxMarks);
                $upsert->execute([$sid, $courseId, $examType, $score]);
            }

            $newStatus   = $action === 'submit' ? 'submitted' : 'draft';
            $submittedAt = $action === 'submit' ? date('Y-m-d H:i:s') : null;

            $pdo->prepare(
                'INSERT INTO exam_submissions (teacher_course_id, exam_type, status, submitted_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), submitted_at=VALUES(submitted_at)'
            )->execute([$courseId, $examType, $newStatus, $submittedAt]);

            $pdo->commit();

            $submission = loadSubmission($pdo, $courseId, $examType);
            $subStatus  = $submission['status'] ?? null;
            $scoreStmt->execute([$courseId, $examType,
                $course['department'], $course['semester'], $course['shift']]);
            $students = $scoreStmt->fetchAll();

            $success = $action === 'submit'
                ? 'Scores submitted for admin approval.'
                : 'Draft saved successfully.';
            log_activity($pdo, 'exam_scores_' . $newStatus,
                $course['subject_name'] . ' — ' . strtoupper($examType) . ' (' . $course['department'] . ' sem ' . $course['semester'] . ')');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Could not save scores. Please try again.';
        }
    }
}

$isLocked = in_array($subStatus, ['submitted', 'approved']);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Page header -->
    <div class="flex justify-between items-start mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Score Entry</h1>
            <p class="fluent-caption mt-1">
                <?= htmlspecialchars($course['subject_name']) ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($course['department'] ?? '—') ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($course['semester']   ?? '—') ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($course['shift']      ?? '—') ?>
            </p>
        </div>
        <a href="exam_result.php" class="fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    <!-- Status banner -->
    <?php if ($subStatus === 'approved'): ?>
    <div class="fluent-alert fluent-alert-success mb-5 fluent-fade-in">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Scores <strong>approved</strong> by admin on <?= date('d M Y, g:i a', strtotime($submission['approved_at'])) ?>.</span>
    </div>
    <?php elseif ($subStatus === 'submitted'): ?>
    <div class="fluent-card px-5 py-3 mb-5 flex items-center justify-between fluent-fade-in"
         style="border-left:3px solid var(--accent);">
        <div class="flex items-center gap-3">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 style="color:var(--accent);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span style="font-size:13px;color:var(--text);">
                <strong>Submitted for approval</strong>
                <span style="color:var(--text-tertiary);margin-left:6px;">
                    <?= date('d M Y, g:i a', strtotime($submission['submitted_at'])) ?>
                </span>
            </span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="recall">
            <button type="submit" class="fluent-btn" style="font-size:12px;padding:4px 12px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
                Recall to Draft
            </button>
        </form>
    </div>
    <?php elseif ($subStatus === 'draft'): ?>
    <div class="fluent-card px-5 py-2.5 mb-5 flex items-center gap-3 fluent-fade-in"
         style="border-left:3px solid var(--text-tertiary);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        <span style="font-size:13px;color:var(--text-secondary);">Draft — not yet submitted to admin.</span>
    </div>
    <?php endif; ?>

    <!-- Alerts -->
    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash>
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger mb-4" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Exam info strip -->
    <div class="fluent-card px-5 py-3 mb-4 flex items-center gap-4 fluent-fade-in" style="animation-delay:40ms;">
        <span class="fluent-badge fluent-badge-success" style="font-size:12px;padding:4px 10px;">
            <?= $examLabel ?>
        </span>
        <span class="fluent-badge" style="font-size:12px;padding:4px 10px;<?= $chance === 'second'
            ? 'background:color-mix(in srgb,#e8a200 12%,transparent);color:#b07800;border:1px solid color-mix(in srgb,#e8a200 35%,transparent);'
            : '' ?>">
            <?= $chance === 'second' ? 'Second Chance' : 'First Chance' ?>
        </span>
        <span style="font-size:13px;color:var(--text-secondary);">
            Max marks: <strong style="color:var(--text);"><?= $maxMarks ?></strong>
        </span>
        <span style="font-size:13px;color:var(--text-secondary);margin-left:auto;">
            <?= count($students) ?> student<?= count($students) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($students)): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in" style="animation-delay:60ms;">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">
            No students found for this department, semester, and shift.
        </p>
    </div>
    <?php else: ?>

    <form method="POST" class="fluent-fade-in" style="animation-delay:60ms;">
        <div class="fluent-card overflow-hidden mb-4">
            <table class="fluent-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th style="width:130px;">Roll No</th>
                        <th>Student Name</th>
                        <th>Father's Name</th>
                        <th style="width:160px;text-align:center;">
                            Score
                            <span style="font-weight:400;font-size:11px;color:var(--text-tertiary);"> / <?= $maxMarks ?></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                <?php $alreadyScoredEarly = $s['score'] !== null && $chance === 'second' && !$isLocked; ?>
                <tr style="<?= $alreadyScoredEarly ? 'opacity:0.55;' : '' ?>">
                    <td style="color:var(--text-tertiary);font-weight:600;"><?= $i + 1 ?></td>
                    <td style="font-family:monospace;font-size:13px;color:var(--text-secondary);">
                        <?= htmlspecialchars($s['roll_no'] ?? '—') ?>
                    </td>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['father_name'] ?? '—') ?></td>
                    <?php
                    $alreadyScored = $s['score'] !== null;
                    $rowLocked     = $isLocked || ($chance === 'second' && $alreadyScored);
                    ?>
                    <td style="text-align:center;">
                        <?php if ($rowLocked): ?>
                            <span style="font-weight:600;font-size:14px;<?= ($chance === 'second' && $alreadyScored && !$isLocked)
                                ? 'color:var(--text-tertiary);' : '' ?>">
                                <?= $alreadyScored
                                    ? htmlspecialchars($s['score'])
                                    : '<span style="color:var(--text-tertiary);">—</span>' ?>
                            </span>
                            <?php if ($chance === 'second' && $alreadyScored && !$isLocked): ?>
                            <div style="font-size:10px;color:var(--text-tertiary);margin-top:2px;">already scored</div>
                            <?php endif; ?>
                        <?php else: ?>
                        <div class="fluent-input" style="max-width:110px;margin:0 auto;">
                            <input type="number"
                                   name="score[<?= (int)$s['sid'] ?>]"
                                   min="0" max="<?= $maxMarks ?>" step="0.5"
                                   value="<?= $alreadyScored ? htmlspecialchars($s['score']) : '' ?>"
                                   placeholder="—"
                                   style="text-align:center;font-weight:600;">
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Action bar — only shown when editable -->
        <?php if (!$isLocked): ?>
        <div class="fluent-card px-5 py-4 flex items-center justify-between">
            <span style="font-size:13px;color:var(--text-secondary);">
                Scores 0 – <?= $maxMarks ?>. Leave blank to skip.
            </span>
            <div class="flex gap-3">
                <a href="exam_result.php" class="fluent-btn" style="font-size:13px;">Cancel</a>

                <button type="submit" name="action" value="draft" class="fluent-btn" style="font-size:13px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 35%,transparent);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Save Draft
                </button>

                <button type="submit" name="action" value="submit" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Submit for Approval
                </button>
            </div>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
