<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shamsi.php';
require_once __DIR__ . '/../includes/departments.php';
require_role('student');

$pageTitle = 'Dashboard — ' . SITE_NAME;
$user = current_user();

// Load student record
$stmt = $pdo->prepare(
    'SELECT s.*, u.name FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?'
);
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

// Count subjects (courses matching dept/sem/shift)
$subjectCount = 0;
$approvedCount = 0;
$recentScores = [];

if ($student) {
    $subjectCount = (int)$pdo->prepare(
        'SELECT COUNT(*) FROM teacher_courses
         WHERE department = ? AND semester = ? AND shift = ?'
    )->execute([$student['department'], $student['semester'], $student['shift']])
     ?: 0;

    $r = $pdo->prepare('SELECT COUNT(*) FROM teacher_courses WHERE department=? AND semester=? AND shift=?');
    $r->execute([$student['department'], $student['semester'], $student['shift']]);
    $subjectCount = (int)$r->fetchColumn();

    $r = $pdo->prepare(
        'SELECT COUNT(*) FROM exam_scores es
         JOIN exam_submissions sub ON sub.teacher_course_id = es.teacher_course_id
             AND sub.exam_type = es.exam_type
         WHERE es.student_id = ? AND sub.status = "approved"'
    );
    $r->execute([$student['id']]);
    $approvedCount = (int)$r->fetchColumn();

    $r = $pdo->prepare(
        'SELECT es.score, es.exam_type, tc.subject_name, sub.approved_at
         FROM exam_scores es
         JOIN teacher_courses tc ON tc.id = es.teacher_course_id
         JOIN exam_submissions sub ON sub.teacher_course_id = es.teacher_course_id
             AND sub.exam_type = es.exam_type
         WHERE es.student_id = ? AND sub.status = "approved"
         ORDER BY sub.approved_at DESC LIMIT 5'
    );
    $r->execute([$student['id']]);
    $recentScores = $r->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Dashboard</h1>
        <p class="fluent-caption mt-1"><?= shamsiDate() ?></p>
    </div>

    <!-- Student profile card -->
    <div class="fluent-card p-6 mb-5 fluent-fade-in" style="animation-delay:40ms;">
        <div class="flex items-center gap-5">
            <div class="fluent-avatar flex-shrink-0" style="width:64px;height:64px;font-size:26px;">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="flex-1">
                <h2 class="fluent-h2 mb-0.5"><?= htmlspecialchars($user['name']) ?></h2>
                <?php if ($student): ?>
                <p class="fluent-caption">
                    <?= htmlspecialchars($student['roll_no'] ?? '—') ?>
                    <?php if ($student['father_name']): ?>
                    &nbsp;·&nbsp; د <?= htmlspecialchars($student['father_name']) ?> زوی/لور
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <?php if ($student): ?>
            <div class="flex gap-2 flex-wrap justify-end">
                <?php if ($student['department']): ?>
                <span class="fluent-badge"><?= dept_label($pdo, $student['department']) ?></span>
                <?php endif; ?>
                <?php if ($student['semester']): ?>
                <span class="fluent-badge fluent-badge-success"><?= htmlspecialchars($student['semester']) ?></span>
                <?php endif; ?>
                <?php if ($student['shift']): ?>
                <span class="fluent-badge" style="background:color-mix(in srgb,#7a3db3 10%,transparent);color:#7a3db3;border:1px solid color-mix(in srgb,#7a3db3 30%,transparent);">
                    <?= htmlspecialchars($student['shift']) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 gap-4 mb-5 fluent-fade-in" style="animation-delay:80ms;">
        <div class="fluent-card p-5 flex items-center gap-4" style="border-left:3px solid var(--accent);">
            <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 style="color:var(--accent);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <div>
                <p style="font-size:28px;font-weight:700;color:var(--text);line-height:1;"><?= $subjectCount ?></p>
                <p class="fluent-caption mt-1">Enrolled Subjects</p>
            </div>
            <a href="subjects.php" class="fluent-btn ml-auto" style="font-size:12px;padding:4px 12px;">View</a>
        </div>
        <div class="fluent-card p-5 flex items-center gap-4" style="border-left:3px solid #1a7f37;">
            <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 style="color:#1a7f37;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <div>
                <p style="font-size:28px;font-weight:700;color:var(--text);line-height:1;"><?= $approvedCount ?></p>
                <p class="fluent-caption mt-1">Approved Results</p>
            </div>
            <a href="scores.php" class="fluent-btn ml-auto" style="font-size:12px;padding:4px 12px;">View</a>
        </div>
    </div>

    <!-- Recent scores -->
    <?php if (!empty($recentScores)): ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:120ms;">
        <div class="px-5 py-3" style="border-bottom:1px solid var(--border);">
            <h3 class="fluent-h3">Recent Results</h3>
        </div>
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Exam</th>
                    <th style="text-align:center;">Score</th>
                    <th>Approved</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentScores as $rs): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($rs['subject_name']) ?></td>
                <td>
                    <span class="fluent-badge <?= $rs['exam_type'] === 'midterm' ? '' : 'fluent-badge-success' ?>"
                          style="text-transform:capitalize;">
                        <?= $rs['exam_type'] ?>
                    </span>
                </td>
                <td style="text-align:center;font-weight:700;font-size:16px;">
                    <?= htmlspecialchars($rs['score']) ?>
                </td>
                <td style="font-size:12px;color:var(--text-tertiary);">
                    <?= shamsiDate($rs['approved_at']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="px-5 py-3" style="border-top:1px solid var(--border);">
            <a href="scores.php" style="font-size:13px;color:var(--accent);text-decoration:none;">
                View all results →
            </a>
        </div>
    </div>
    <?php elseif ($student): ?>
    <div class="fluent-card p-8 text-center fluent-fade-in" style="animation-delay:120ms;">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No approved results yet.</p>
    </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
