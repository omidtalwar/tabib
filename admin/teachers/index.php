<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Teachers — ' . SITE_NAME;

$teachers = $pdo->query(
    'SELECT u.id, u.name, u.email, u.status, t.qualification, t.joining_date
     FROM users u JOIN teachers t ON t.user_id = u.id ORDER BY u.name'
)->fetchAll();
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-xl font-bold text-gray-800">Teachers</h1>
        <a href="add.php"
           class="bg-blue-800 hover:bg-blue-900 text-white text-sm px-4 py-2 rounded-lg transition">
            + Add Teacher
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="bg-green-100 text-green-800 border border-green-300 rounded-lg px-4 py-3 mb-5 text-sm" data-flash>
        Teacher deleted successfully.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'self'): ?>
    <div class="bg-red-50 text-red-700 border border-red-300 rounded-lg px-4 py-3 mb-5 text-sm" data-flash>
        You cannot delete your own account.
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Name</th>
                    <th class="px-4 py-3 text-left font-medium">Email</th>
                    <th class="px-4 py-3 text-left font-medium">Qualification</th>
                    <th class="px-4 py-3 text-left font-medium">Joined</th>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                    <th class="px-4 py-3 text-left font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($teachers)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No teachers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($teachers as $i => $t): ?>
            <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> border-b border-gray-100 hover:bg-blue-50 transition">
                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($t['name']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($t['email']) ?></td>
                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($t['qualification'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-500"><?= $t['joining_date'] ?? '—' ?></td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                        <?= $t['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                        <?= $t['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <a href="edit.php?id=<?= $t['id'] ?>"
                           class="text-blue-600 hover:underline text-xs font-medium">Edit</a>
                        <a href="courses.php?id=<?= $t['id'] ?>"
                           class="text-emerald-600 hover:underline text-xs font-medium">Courses</a>
                        <form method="POST" action="delete.php"
                              onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($t['name'])) ?>? This cannot be undone.')">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit"
                                    class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
