<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare(
    'SELECT es.*, tc.subject_name, tc.department, tc.semester, tc.shift, tc.credits,
            u.name AS teacher_name,
            approver.name AS approver_name
     FROM exam_submissions es
     JOIN teacher_courses tc ON tc.id = es.teacher_course_id
     JOIN teachers t ON t.id = tc.teacher_id
     JOIN users u ON u.id = t.user_id
     LEFT JOIN users approver ON approver.id = es.approved_by
     WHERE es.id = ?'
);
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub) { header('Location: index.php'); exit; }

$maxMarks  = $sub['exam_type'] === 'midterm' ? 20 : 80;
$examLabel = $sub['exam_type'] === 'midterm' ? 'Midterm Exam' : 'Final Exam';

$scores = $pdo->prepare(
    'SELECT s.roll_no, u.name, s.father_name, sc.score
     FROM exam_scores sc
     JOIN students s ON s.id = sc.student_id
     JOIN users u ON u.id = s.user_id
     WHERE sc.teacher_course_id = ? AND sc.exam_type = ?
     ORDER BY s.roll_no ASC'
);
$scores->execute([$sub['teacher_course_id'], $sub['exam_type']]);
$students = $scores->fetchAll();

$pageTitle = 'View Scores — ' . SITE_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-start mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">View Scores</h1>
            <p class="fluent-caption mt-1">
                <?= htmlspecialchars($sub['subject_name']) ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($sub['department'] ?? '—') ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($sub['semester']   ?? '—') ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($sub['shift']      ?? '—') ?>
            </p>
        </div>
        <a href="index.php" class="fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    <!-- Info strip -->
    <div class="fluent-card px-5 py-3 mb-5 flex flex-wrap items-center gap-4 fluent-fade-in" style="animation-delay:40ms;">
        <span class="fluent-badge fluent-badge-success"><?= $examLabel ?></span>
        <span style="font-size:13px;color:var(--text-secondary);">
            Teacher: <strong style="color:var(--text);"><?= htmlspecialchars($sub['teacher_name']) ?></strong>
        </span>
        <span style="font-size:13px;color:var(--text-secondary);">
            Max: <strong style="color:var(--text);"><?= $maxMarks ?></strong>
        </span>
        <?php if ($sub['status'] === 'approved'): ?>
        <span class="fluent-badge fluent-badge-success" style="margin-left:auto;">
            Approved — <?= date('d M Y', strtotime($sub['approved_at'])) ?>
        </span>
        <?php elseif ($sub['status'] === 'submitted'): ?>
        <span style="margin-left:auto;">
            <form method="POST" action="approve.php" onsubmit="return confirm('Approve these scores?')">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Approve All
                </button>
            </form>
        </span>
        <?php endif; ?>
    </div>

    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th style="width:130px;">Roll No</th>
                    <th>Student Name</th>
                    <th>Father's Name</th>
                    <th style="width:120px;text-align:center;">
                        Score <span style="font-weight:400;font-size:11px;color:var(--text-tertiary);">/ <?= $maxMarks ?></span>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
            <tr>
                <td colspan="5" style="text-align:center;color:var(--text-tertiary);padding:32px;">
                    No scores recorded yet.
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($students as $i => $s): ?>
            <tr>
                <td style="color:var(--text-tertiary);font-weight:600;"><?= $i + 1 ?></td>
                <td style="font-family:monospace;font-size:13px;color:var(--text-secondary);">
                    <?= htmlspecialchars($s['roll_no'] ?? '—') ?>
                </td>
                <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['father_name'] ?? '—') ?></td>
                <td style="text-align:center;font-weight:700;font-size:15px;">
                    <?= $s['score'] !== null
                        ? htmlspecialchars($s['score'])
                        : '<span style="color:var(--text-tertiary);font-weight:400;font-size:13px;">—</span>' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
