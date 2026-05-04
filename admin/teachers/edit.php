<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: index.php'); exit; }

// Load existing data
$stmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.status, t.id AS teacher_id, t.qualification, t.joining_date
     FROM users u JOIN teachers t ON t.user_id = u.id WHERE u.id = ?'
);
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) { header('Location: index.php'); exit; }

$pageTitle = 'Edit Teacher — ' . SITE_NAME;
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']          ?? '');
    $email   = trim($_POST['email']         ?? '');
    $password= trim($_POST['password']      ?? '');
    $qual    = trim($_POST['qualification'] ?? '');
    $joined  = $_POST['joining_date']       ?? null;
    $status  = isset($_POST['status']) ? 1 : 0;

    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($password) {
                $pdo->prepare('UPDATE users SET name=?, email=?, password=?, status=? WHERE id=?')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $status, $userId]);
            } else {
                $pdo->prepare('UPDATE users SET name=?, email=?, status=? WHERE id=?')
                    ->execute([$name, $email, $status, $userId]);
            }

            $pdo->prepare('UPDATE teachers SET qualification=?, joining_date=? WHERE id=?')
                ->execute([$qual ?: null, $joined ?: null, $row['teacher_id']]);

            $pdo->commit();

            // Reload fresh data
            $stmt->execute([$userId]);
            $row     = $stmt->fetch();
            $success = 'Teacher updated successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Email already in use.' : 'Could not update teacher.';
        }
    }
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-xl font-bold text-gray-800">Edit Teacher</h1>
        <a href="index.php" class="text-sm text-blue-600 hover:underline">← Back to list</a>
    </div>

    <?php if ($success): ?>
    <div class="bg-green-100 text-green-800 border border-green-300 rounded-lg px-4 py-3 mb-5 text-sm" data-flash>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 text-red-700 border border-red-300 rounded-lg px-4 py-3 mb-5 text-sm" data-flash>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
        <form method="POST" class="space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input type="text" name="name" required
                       value="<?= htmlspecialchars($row['name']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($row['email']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password
                    <span class="text-gray-400 font-normal">(leave blank to keep current)</span>
                </label>
                <input type="password" name="password" minlength="6"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                <input type="text" name="qualification"
                       value="<?= htmlspecialchars($row['qualification'] ?? '') ?>"
                       placeholder="e.g. M.Sc. Mathematics"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date</label>
                <input type="date" name="joining_date"
                       value="<?= htmlspecialchars($row['joining_date'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="status" id="status" value="1"
                       <?= $row['status'] ? 'checked' : '' ?>
                       class="w-4 h-4 accent-blue-700">
                <label for="status" class="text-sm text-gray-700">Active</label>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-blue-800 hover:bg-blue-900 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                    Save Changes
                </button>
                <a href="courses.php?id=<?= $userId ?>"
                   class="text-emerald-600 hover:underline text-sm font-medium">
                    Manage Courses →
                </a>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
