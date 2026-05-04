<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Add Teacher — ' . SITE_NAME;
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']         ?? '');
    $email   = trim($_POST['email']        ?? '');
    $password= trim($_POST['password']     ?? '');
    $qual    = trim($_POST['qualification'] ?? '');
    $joined  = $_POST['joining_date']      ?? null;

    if (!$name || !$email || !$password) {
        $error = 'Name, email, and password are required.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "teacher")');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO teachers (user_id, qualification, joining_date) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $qual ?: null, $joined ?: null]);
            $pdo->commit();
            $success = 'Teacher added successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Email already exists.' : 'Could not save teacher.';
        }
    }
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-xl font-bold text-gray-800">Add Teacher</h1>
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
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input type="email" name="email" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
                <input type="text" name="qualification" placeholder="e.g. M.Sc. Mathematics"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Joining Date</label>
                <input type="date" name="joining_date"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit"
                    class="bg-blue-800 hover:bg-blue-900 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                Save Teacher
            </button>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
