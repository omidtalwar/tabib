<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Students — ' . SITE_NAME;

$students = $pdo->query(
    'SELECT u.id, u.name, u.email, u.status, s.roll_no, c.name AS class_name
     FROM users u JOIN students s ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id ORDER BY u.name'
)->fetchAll();
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-xl font-bold text-gray-800">Students</h1>
        <a href="add.php"
           class="bg-blue-800 hover:bg-blue-900 text-white text-sm px-4 py-2 rounded-lg transition">
            + Add Student
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Name</th>
                    <th class="px-4 py-3 text-left font-medium">Roll No</th>
                    <th class="px-4 py-3 text-left font-medium">Class</th>
                    <th class="px-4 py-3 text-left font-medium">Email</th>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No students found.</td></tr>
            <?php endif; ?>
            <?php foreach ($students as $i => $s): ?>
            <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> border-b border-gray-100 hover:bg-blue-50 transition">
                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($s['name']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($s['roll_no'] ?? '—') ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($s['class_name'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($s['email']) ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                        <?= $s['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                        <?= $s['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
