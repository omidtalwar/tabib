<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Departments — ' . SITE_NAME;
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']        ?? '';
    $id      = (int)($_POST['id']      ?? 0);
    $nameEn  = trim($_POST['name_en']  ?? '');
    $namePs  = trim($_POST['name_ps']  ?? '');
    $maxSem  = max(1, min(12, (int)($_POST['max_semesters'] ?? 4)));
    $sortOrd = (int)($_POST['sort_order'] ?? 0);

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM departments WHERE id=?')->execute([$id]);
        $success = 'Department deleted.';
    } elseif ($action === 'save') {
        if (!$nameEn) {
            $error = 'English name is required.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE departments SET name_en=?,name_ps=?,max_semesters=?,sort_order=? WHERE id=?'
                    )->execute([$nameEn, $namePs ?: null, $maxSem, $sortOrd, $id]);
                    $success = "Department updated.";
                } else {
                    $pdo->prepare(
                        'INSERT INTO departments (name_en,name_ps,max_semesters,sort_order) VALUES (?,?,?,?)'
                    )->execute([$nameEn, $namePs ?: null, $maxSem, $sortOrd]);
                    $success = "Department \"$nameEn\" added.";
                }
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate')
                    ? 'A department with this English name already exists.'
                    : 'Could not save department.';
            }
        }
    }
}

$departments = [];
try {
    $departments = $pdo->query(
        'SELECT * FROM departments ORDER BY sort_order, name_en'
    )->fetchAll();
} catch (Exception $e) {
    $error = 'Departments table not found — please run database/migrate_departments_materials.sql first.';
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Departments</h1>
        <p class="fluent-caption mt-1">Manage department names used across the system.</p>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger mb-4" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="flex gap-5 fluent-fade-in" style="animation-delay:40ms;align-items:flex-start;">

        <!-- Add / Edit Form -->
        <div class="fluent-card p-5 flex-shrink-0" style="width:360px;">
            <h2 class="fluent-label mb-4" id="formTitle" style="font-size:13px;font-weight:700;letter-spacing:.04em;">ADD DEPARTMENT</h2>
            <form method="POST" class="space-y-4" id="deptForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="editId" value="0">

                <div>
                    <label class="fluent-label block mb-1.5">English Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="name_en" id="fNameEn" required placeholder="e.g. Pharmacy">
                    </div>
                </div>

                <div>
                    <label class="fluent-label block mb-1.5">Pashto Name</label>
                    <div class="fluent-input">
                        <input type="text" name="name_ps" id="fNamePs" dir="rtl"
                               placeholder="درملپوهنه" style="font-size:15px;">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="fluent-label block mb-1.5">Max Semesters</label>
                        <div class="fluent-input">
                            <input type="number" name="max_semesters" id="fMaxSem" min="1" max="12" value="4">
                        </div>
                    </div>
                    <div>
                        <label class="fluent-label block mb-1.5">Sort Order</label>
                        <div class="fluent-input">
                            <input type="number" name="sort_order" id="fSortOrd" min="0" value="0">
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="fluent-btn-accent fluent-btn" id="submitBtn">Add Department</button>
                    <button type="button" class="fluent-btn hidden" id="cancelBtn"
                            onclick="resetForm()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="fluent-card overflow-hidden flex-1">
            <table class="fluent-table">
                <thead>
                    <tr>
                        <th>English Name</th>
                        <th>Pashto Name</th>
                        <th style="text-align:center;">Max Sem.</th>
                        <th style="text-align:center;">Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($departments)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:40px;color:var(--text-tertiary);">
                        No departments yet. Add the first one.
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($departments as $d): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($d['name_en']) ?></td>
                    <td dir="rtl" style="font-size:15px;text-align:right;">
                        <?= htmlspecialchars($d['name_ps'] ?? '—') ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="fluent-badge"><?= (int)$d['max_semesters'] ?></span>
                    </td>
                    <td style="text-align:center;color:var(--text-tertiary);"><?= (int)$d['sort_order'] ?></td>
                    <td>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    class="fluent-btn" style="padding:3px 10px;font-size:12px;"
                                    onclick="editDept(<?= $d['id'] ?>,<?= htmlspecialchars(json_encode($d['name_en'])) ?>,<?= htmlspecialchars(json_encode($d['name_ps'] ?? '')) ?>,<?= (int)$d['max_semesters'] ?>,<?= (int)$d['sort_order'] ?>)">
                                Edit
                            </button>
                            <form method="POST"
                                  onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($d['name_en'])) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="fluent-btn"
                                        style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
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
    </div>
</main>

<script>
function editDept(id, nameEn, namePs, maxSem, sortOrd) {
    document.getElementById('editId').value   = id;
    document.getElementById('fNameEn').value  = nameEn;
    document.getElementById('fNamePs').value  = namePs;
    document.getElementById('fMaxSem').value  = maxSem;
    document.getElementById('fSortOrd').value = sortOrd;
    document.getElementById('formTitle').textContent  = 'EDIT DEPARTMENT';
    document.getElementById('submitBtn').textContent  = 'Update Department';
    document.getElementById('cancelBtn').classList.remove('hidden');
    document.getElementById('deptForm').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
    document.getElementById('editId').value   = '0';
    document.getElementById('fNameEn').value  = '';
    document.getElementById('fNamePs').value  = '';
    document.getElementById('fMaxSem').value  = '4';
    document.getElementById('fSortOrd').value = '0';
    document.getElementById('formTitle').textContent = 'ADD DEPARTMENT';
    document.getElementById('submitBtn').textContent = 'Add Department';
    document.getElementById('cancelBtn').classList.add('hidden');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
