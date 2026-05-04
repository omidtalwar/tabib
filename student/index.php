<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pageTitle = 'Dashboard — ' . SITE_NAME;
$user = current_user();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <div class="bg-white rounded-xl shadow-sm p-6 text-gray-500">
        Welcome, <?= htmlspecialchars($user['name']) ?>. More features coming soon.
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
