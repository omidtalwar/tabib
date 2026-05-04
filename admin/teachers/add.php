<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Add Teacher — ' . SITE_NAME;
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']          ?? '');
    $email   = trim($_POST['email']         ?? '');
    $password= trim($_POST['password']      ?? '');
    $qual    = trim($_POST['qualification'] ?? '');
    $joined  = $_POST['joining_date']       ?? null;

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

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Add Teacher</h1>
            <p class="fluent-caption mt-1">Register a new teacher account.</p>
        </div>
        <a href="index.php" class="fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="fluent-card p-6 max-w-lg fluent-fade-in" style="animation-delay:60ms;">
        <form method="POST" class="space-y-5">

            <div>
                <label class="fluent-label block mb-1.5">Full Name *</label>
                <div class="fluent-input">
                    <input type="text" name="name" required placeholder="e.g. Dr. John Smith">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Email Address *</label>
                <div class="fluent-input">
                    <input type="email" name="email" required placeholder="teacher@school.edu">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Password *</label>
                <div class="fluent-input">
                    <input type="password" name="password" required minlength="6" placeholder="Min. 6 characters">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Qualification</label>
                <div class="fluent-input">
                    <input type="text" name="qualification" placeholder="e.g. M.Sc. Mathematics">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Joining Date</label>
                <div class="fluent-input">
                    <input type="date" name="joining_date">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="fluent-btn-accent fluent-btn">Save Teacher</button>
                <a href="index.php" class="fluent-btn">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
