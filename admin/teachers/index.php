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

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Teachers</h1>
            <p class="fluent-caption mt-1"><?= count($teachers) ?> registered teacher<?= count($teachers) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="add.php" class="fluent-btn-accent fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Teacher
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="fluent-alert fluent-alert-success" data-flash>Teacher deleted successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'self'): ?>
    <div class="fluent-alert fluent-alert-danger" data-flash>You cannot delete your own account.</div>
    <?php endif; ?>

    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Qualification</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($teachers)): ?>
            <tr>
                <td colspan="6" style="text-align:center; padding: 40px 16px; color: var(--text-tertiary);">
                    No teachers found. <a href="add.php" style="color:var(--accent);">Add the first one →</a>
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($teachers as $t): ?>
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="fluent-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0;">
                            <?= strtoupper(substr($t['name'], 0, 1)) ?>
                        </div>
                        <span style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></span>
                    </div>
                </td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['email']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['qualification'] ?? '—') ?></td>
                <td style="color:var(--text-secondary);"><?= $t['joining_date'] ?? '—' ?></td>
                <td>
                    <span class="fluent-badge <?= $t['status'] ? 'fluent-badge-success' : 'fluent-badge-danger' ?>">
                        <?= $t['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <div class="flex items-center gap-3">
                        <a href="edit.php?id=<?= $t['id'] ?>"
                           class="fluent-btn" style="padding:4px 12px;font-size:12px;">Edit</a>
                        <a href="courses.php?id=<?= $t['id'] ?>"
                           class="fluent-btn" style="padding:4px 12px;font-size:12px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 30%,transparent);">Courses</a>
                        <form method="POST" action="delete.php"
                              onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($t['name'])) ?>? This cannot be undone.')">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="fluent-btn"
                                    style="padding:4px 12px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                                Delete
                            </button>
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
