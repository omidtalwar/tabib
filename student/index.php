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

    <div class="mb-6 fluent-fade-in">
        <h1 class="fluent-h1">Dashboard</h1>
        <p class="fluent-caption mt-1">Welcome, <?= htmlspecialchars($user['name']) ?>.</p>
    </div>

    <div class="fluent-card p-8 text-center fluent-fade-in" style="animation-delay:80ms;">
        <div class="fluent-avatar mx-auto mb-4" style="width:56px;height:56px;font-size:22px;">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <h2 class="fluent-h2 mb-2"><?= htmlspecialchars($user['name']) ?></h2>
        <p class="fluent-caption">Student portal — more features coming soon.</p>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
