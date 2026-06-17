<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Subjects — ' . SITE_NAME;
$error = $success = '';

require_once __DIR__ . '/../../includes/departments.php';
$allDepts    = get_departments($pdo);
$departments = dept_names_en($pdo);
$semestersBase  = ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
$semestersExtra = ['5th Semester','6th Semester'];

/* ── POST handlers ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $name   = trim($_POST['name']       ?? '');
        $dept   = trim($_POST['department'] ?? '');
        $sem    = trim($_POST['semester']   ?? '');
        $credits= (int)($_POST['credits']   ?? 1);

        if (!$name || !$dept || !$sem) {
            $error = 'Subject name, department, and semester are required.';
        } else {
            $pdo->prepare('INSERT INTO subjects (name, department, semester, credits) VALUES (?, ?, ?, ?)')
                ->execute([$name, $dept, $sem, $credits]);
            $success = "Subject \"$name\" added successfully.";
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['subject_id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
        }
        header('Location: index.php?deleted=1');
        exit;
    }
}

if (isset($_GET['deleted'])) $success = 'Subject deleted.';

/* ── Load subjects (grouped by dept → sem) ─────────────────── */
$rows = $pdo->query(
    'SELECT * FROM subjects ORDER BY department, semester, name'
)->fetchAll();

// Group for display
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['department']][$r['semester']][] = $r;
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Subject Management</h1>
            <p class="fluent-caption mt-1"><?= count($rows) ?> subject<?= count($rows) !== 1 ? 's' : '' ?> in the catalog</p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ── Add subject form ────────────────────────────── -->
        <div class="lg:col-span-1">
            <div class="fluent-card p-5 fluent-fade-in" style="animation-delay:40ms;">
                <h2 class="fluent-h3 mb-4">Add New Subject</h2>
                <form method="POST" class="space-y-4" id="addSubjectForm">
                    <input type="hidden" name="_action" value="add">

                    <div>
                        <label class="fluent-label block mb-1.5">Subject Name *</label>
                        <div class="fluent-input">
                            <input type="text" name="name" required placeholder="e.g. Anatomy">
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Department *</label>
                        <div class="fluent-input">
                            <select name="department" id="formDept" required>
                                <option value="">— Select —</option>
                                <?php foreach ($departments as $d): ?>
                                <option><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Semester *</label>
                        <div class="fluent-input">
                            <select name="semester" id="formSem" required>
                                <option value="">— Select department first —</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Credits (weekly classes)</label>
                        <div class="fluent-input">
                            <input type="number" name="credits" value="3" min="1" max="20">
                        </div>
                    </div>

                    <button type="submit" class="fluent-btn-accent fluent-btn w-full" style="justify-content:center;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Subject
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Subject list ────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-4 fluent-stagger">
            <?php if (empty($grouped)): ?>
            <div class="fluent-card p-10 text-center">
                <p class="fluent-body" style="color:var(--text-tertiary);">
                    No subjects yet. Add your first one using the form.
                </p>
            </div>
            <?php endif; ?>

            <?php foreach ($grouped as $dept => $semesters): ?>
            <div class="fluent-card overflow-hidden">
                <!-- Department header -->
                <div class="flex items-center gap-3 px-5 py-3"
                     style="background:color-mix(in srgb,var(--accent) 6%,transparent); border-bottom:1px solid var(--border);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--accent);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                    </svg>
                    <h3 class="fluent-h3" style="font-size:14px;"><?= htmlspecialchars($dept) ?></h3>
                </div>

                <table class="fluent-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Semester</th>
                            <th style="width:80px;text-align:center;">Credits</th>
                            <th style="width:80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($semesters as $sem => $subjects): ?>
                    <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                        <td>
                            <span class="fluent-badge"><?= htmlspecialchars($s['semester']) ?></span>
                        </td>
                        <td style="text-align:center;">
                            <span class="fluent-badge fluent-badge-success"><?= (int)$s['credits'] ?></span>
                        </td>
                        <td>
                            <form method="POST"
                                  onsubmit="return confirm('Delete \'<?= htmlspecialchars(addslashes($s['name'])) ?>\'?')">
                                <input type="hidden" name="_action"    value="delete">
                                <input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="fluent-btn"
                                        style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</main>

<script>
const semBase  = ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
const semExtra = ['5th Semester','6th Semester'];

document.getElementById('formDept').addEventListener('change', function () {
    const sel  = document.getElementById('formSem');
    const opts = this.value === 'Nursing' ? semBase.concat(semExtra) : semBase;
    sel.innerHTML = '<option value="">— Select semester —</option>';
    opts.forEach(function (o) {
        const el = document.createElement('option');
        el.textContent = o;
        sel.appendChild(el);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
