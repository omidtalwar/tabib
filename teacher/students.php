<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'My Students — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher   = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

$students = [];
if ($teacherId) {
    $stmt = $pdo->prepare(
        'SELECT u.name, u.email, s.roll_no, c.name AS class_name
         FROM students s
         JOIN users   u ON s.user_id  = u.id
         JOIN classes c ON s.class_id = c.id
         WHERE c.teacher_id = ?
         ORDER BY c.name, u.name'
    );
    $stmt->execute([$teacherId]);
    $students = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <h1 class="text-xl font-bold text-gray-800 mb-5">My Students</h1>

    <?php if (empty($students)): ?>
    <div class="bg-white rounded-xl shadow-sm p-6 text-gray-500 text-sm">No students assigned yet.</div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Name</th>
                    <th class="px-4 py-3 text-left font-medium">Roll No</th>
                    <th class="px-4 py-3 text-left font-medium">Class</th>
                    <th class="px-4 py-3 text-left font-medium">Email</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $i => $s): ?>
            <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> border-b border-gray-100">
                <td class="px-4 py-3"><?= htmlspecialchars($s['name']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($s['roll_no'] ?? '-') ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($s['class_name']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($s['email']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
