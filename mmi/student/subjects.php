<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shamsi.php';
require_role('student');

$pageTitle = 'My Subjects — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT * FROM students WHERE user_id = ?');
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

$subjects = [];
if ($student && $student['department']) {
    $stmt = $pdo->prepare(
        'SELECT tc.no, tc.subject_name, tc.department, tc.semester, tc.shift, tc.credits,
                u.name AS teacher_name
         FROM teacher_courses tc
         JOIN teachers t ON t.id = tc.teacher_id
         JOIN users u ON u.id = t.user_id
         WHERE tc.department = ? AND tc.semester = ? AND tc.shift = ?
         ORDER BY tc.no, tc.id'
    );
    $stmt->execute([$student['department'], $student['semester'], $student['shift']]);
    $subjects = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">My Subjects</h1>
        <p class="fluent-caption mt-1">
            <?php if ($student): ?>
                <?= htmlspecialchars($student['department'] ?? '—') ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($student['semester']   ?? '—') ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($student['shift']      ?? '—') ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if (empty($subjects)): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No subjects assigned yet.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Shift</th>
                    <th style="text-align:center;">Credits</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
            <tr>
                <td style="color:var(--text-tertiary);font-weight:600;"><?= (int)$s['no'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($s['subject_name']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['teacher_name']) ?></td>
                <td>
                    <span class="fluent-badge" style="background:color-mix(in srgb,#7a3db3 10%,transparent);color:#7a3db3;border:1px solid color-mix(in srgb,#7a3db3 30%,transparent);">
                        <?= htmlspecialchars($s['shift'] ?? '—') ?>
                    </span>
                </td>
                <td style="text-align:center;">
                    <span class="fluent-badge fluent-badge-success"><?= (int)$s['credits'] ?> cr</span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
