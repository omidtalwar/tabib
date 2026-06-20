<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'My Courses — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

$courses = [];
if ($teacher) {
    $stmt = $pdo->prepare('SELECT * FROM teacher_courses WHERE teacher_id = ? ORDER BY no, id');
    $stmt->execute([$teacher['id']]);
    $courses = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">My Courses</h1>
        <p class="fluent-caption mt-1">Subjects assigned to you by the administration.</p>
    </div>

    <?php if (empty($courses)): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No courses assigned yet.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th>Subject Name</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Shift</th>
                    <th>Credits / Week</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
            <tr>
                <td style="color:var(--text-tertiary);font-weight:600;"><?= (int)$c['no'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($c['subject_name']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['department'] ?? '—') ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['semester'] ?? '—') ?></td>
                <td>
                    <?php if ($c['shift']): ?>
                    <span class="fluent-badge"><?= htmlspecialchars($c['shift']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <span class="fluent-badge fluent-badge-success"><?= (int)$c['credits'] ?> credits</span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
