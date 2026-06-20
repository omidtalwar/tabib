<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shamsi.php';
require_role('student');

$pageTitle = 'My Results — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT * FROM students WHERE user_id = ?');
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

$scores = [];
if ($student) {
    $stmt = $pdo->prepare(
        'SELECT es.score, es.exam_type,
                tc.subject_name, tc.department, tc.semester, tc.credits,
                sub.status AS sub_status, sub.approved_at, sub.submitted_at
         FROM exam_scores es
         JOIN teacher_courses tc ON tc.id = es.teacher_course_id
         LEFT JOIN exam_submissions sub
             ON sub.teacher_course_id = es.teacher_course_id
             AND sub.exam_type = es.exam_type
         WHERE es.student_id = ?
         ORDER BY tc.subject_name, es.exam_type'
    );
    $stmt->execute([$student['id']]);
    $scores = $stmt->fetchAll();
}

$maxMarks = ['midterm' => 20, 'final' => 80];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">My Results</h1>
        <p class="fluent-caption mt-1">Exam scores approved by admin are shown below.</p>
    </div>

    <?php if (empty($scores)): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No results available yet.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th style="text-align:center;">Exam</th>
                    <th style="text-align:center;">Score</th>
                    <th style="text-align:center;">Out of</th>
                    <th style="text-align:center;">Percentage</th>
                    <th style="text-align:center;">Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($scores as $sc):
                $max     = $maxMarks[$sc['exam_type']] ?? 100;
                $approved = $sc['sub_status'] === 'approved';
                $pct     = ($approved && $sc['score'] !== null)
                           ? round(($sc['score'] / $max) * 100, 1) : null;
                $pass    = $pct !== null ? ($pct >= 50) : null;
            ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($sc['subject_name']) ?></td>
                <td style="text-align:center;">
                    <span class="fluent-badge" style="text-transform:capitalize;">
                        <?= $sc['exam_type'] ?>
                    </span>
                </td>
                <td style="text-align:center;font-size:18px;font-weight:700;">
                    <?php if ($approved && $sc['score'] !== null): ?>
                        <span style="color:<?= $pass ? '#1a7f37' : '#c42b1c' ?>;">
                            <?= htmlspecialchars($sc['score']) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:var(--text-tertiary);font-size:13px;font-weight:400;">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;color:var(--text-tertiary);"><?= $max ?></td>
                <td style="text-align:center;font-weight:600;">
                    <?= $pct !== null ? $pct . '%' : '—' ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($sc['sub_status'] === 'approved'): ?>
                        <span class="fluent-badge fluent-badge-success">Approved</span>
                    <?php elseif ($sc['sub_status'] === 'submitted'): ?>
                        <span class="fluent-badge" style="background:color-mix(in srgb,#0f6cbd 10%,transparent);color:#0f6cbd;border:1px solid color-mix(in srgb,#0f6cbd 30%,transparent);">
                            Under Review
                        </span>
                    <?php else: ?>
                        <span class="fluent-badge">Pending</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text-tertiary);">
                    <?= $approved && $sc['approved_at'] ? shamsiDate($sc['approved_at']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary -->
    <?php
    $approvedScores = array_filter($scores, fn($s) => $s['sub_status'] === 'approved' && $s['score'] !== null);
    if (count($approvedScores) > 0):
        $totalPct = array_sum(array_map(function($s) use ($maxMarks) {
            return ($s['score'] / ($maxMarks[$s['exam_type']] ?? 100)) * 100;
        }, $approvedScores));
        $avgPct = round($totalPct / count($approvedScores), 1);
    ?>
    <div class="fluent-card px-5 py-4 mt-4 flex items-center gap-6 fluent-fade-in" style="animation-delay:80ms;">
        <span style="font-size:13px;color:var(--text-secondary);">
            <strong style="color:var(--text);"><?= count($approvedScores) ?></strong> approved result<?= count($approvedScores) !== 1 ? 's' : '' ?>
        </span>
        <span style="font-size:13px;color:var(--text-secondary);">
            Average: <strong style="color:var(--text);font-size:16px;"><?= $avgPct ?>%</strong>
        </span>
        <span style="font-size:13px;color:var(--text-secondary);margin-left:auto;">
            Overall:
            <strong style="color:<?= $avgPct >= 50 ? '#1a7f37' : '#c42b1c' ?>;font-size:15px;">
                <?= $avgPct >= 50 ? 'Pass' : 'Fail' ?>
            </strong>
        </span>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
