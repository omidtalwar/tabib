<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'My Courses — ' . SITE_NAME;

$user = current_user();

// Get teacher record
$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

$courses = [];
if ($teacher) {
    $stmt = $pdo->prepare(
        'SELECT * FROM teacher_courses WHERE teacher_id = ? ORDER BY no, id'
    );
    $stmt->execute([$teacher['id']]);
    $courses = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-800">My Courses</h1>
        <p class="text-sm text-gray-500 mt-0.5">Subjects assigned to you by the administration.</p>
    </div>

    <?php if (empty($courses)): ?>
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        No courses have been assigned to you yet.
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="px-4 py-3 text-left font-medium w-16">No.</th>
                    <th class="px-4 py-3 text-left font-medium">Subject Name</th>
                    <th class="px-4 py-3 text-left font-medium">Department</th>
                    <th class="px-4 py-3 text-left font-medium">Semester</th>
                    <th class="px-4 py-3 text-left font-medium">Shift</th>
                    <th class="px-4 py-3 text-left font-medium">Credits / Week</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $i => $c): ?>
            <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> border-b border-gray-100 hover:bg-blue-50 transition">
                <td class="px-4 py-3 text-gray-500"><?= (int)$c['no'] ?></td>
                <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($c['subject_name']) ?></td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['department'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['semester'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['shift'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                        <?= (int)$c['credits'] ?> credits
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
