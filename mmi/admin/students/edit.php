<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.status,
            s.id AS sid, s.roll_no, s.father_name, s.department, s.semester, s.shift
     FROM users u JOIN students s ON s.user_id = u.id WHERE u.id = ?'
);
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) { header('Location: index.php'); exit; }

$pageTitle   = 'Edit Student — ' . SITE_NAME;
$error = $success = '';

require_once __DIR__ . '/../../includes/departments.php';
$allDepts    = get_departments($pdo);
$departments = dept_names_en($pdo);
$semBase     = ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
$semExtra    = ['5th Semester','6th Semester'];
$shifts      = ['06:00 – 09:00','09:00 – 12:00','01:00 – 04:00'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']        ?? '');
    $fatherName = trim($_POST['father_name'] ?? '');
    $password   = trim($_POST['password']    ?? '');
    $rollNo     = trim($_POST['roll_no']     ?? '');
    $dept       = trim($_POST['department']  ?? '');
    $sem        = trim($_POST['semester']    ?? '');
    $shift      = trim($_POST['shift']       ?? '');
    $status     = isset($_POST['status']) ? 1 : 0;

    if (!$name) {
        $error = 'Name is required.';
    } else {
        try {
            $pdo->beginTransaction();
            if ($password) {
                $pdo->prepare('UPDATE users SET name=?, password=?, status=? WHERE id=?')
                    ->execute([$name, password_hash($password, PASSWORD_DEFAULT), $status, $userId]);
            } else {
                $pdo->prepare('UPDATE users SET name=?, status=? WHERE id=?')
                    ->execute([$name, $status, $userId]);
            }
            $pdo->prepare(
                'UPDATE students SET roll_no=?, father_name=?, department=?, semester=?, shift=? WHERE id=?'
            )->execute([$rollNo ?: null, $fatherName ?: null, $dept ?: null, $sem ?: null, $shift ?: null, $row['sid']]);
            $pdo->commit();
            $stmt->execute([$userId]);
            $row     = $stmt->fetch();
            $success = 'Student updated successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Roll number already in use.' : 'Could not update student.';
        }
    }
}

// Use POST values on failed submit, otherwise use DB values
$v = $_SERVER['REQUEST_METHOD'] === 'POST' && $error ? $_POST : $row;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Edit Student</h1>
            <p class="fluent-caption mt-1"><?= htmlspecialchars($row['name']) ?></p>
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

    <div class="fluent-card p-6 max-w-xl fluent-fade-in" style="animation-delay:60ms;">
        <form method="POST" class="space-y-5">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Full Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="name" required value="<?= htmlspecialchars($v['name']) ?>">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Father's Name</label>
                    <div class="fluent-input">
                        <input type="text" name="father_name" value="<?= htmlspecialchars($v['father_name'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div>
                    <label class="fluent-label block mb-1.5">Roll No</label>
                    <div class="flex gap-2">
                        <div class="fluent-input flex-1">
                            <input type="text" name="roll_no" id="rollNoInput"
                                   value="<?= htmlspecialchars($v['roll_no'] ?? '') ?>"
                                   placeholder="MMI-00001">
                        </div>
                        <button type="button" id="genRollBtn" class="fluent-btn"
                                style="font-size:12px;padding:0 12px;white-space:nowrap;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 35%,transparent);">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Generate
                        </button>
                    </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">
                    New Password
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;color:var(--text-tertiary);"> — leave blank to keep current</span>
                </label>
                <div class="fluent-input">
                    <input type="password" name="password" minlength="6" placeholder="New password">
                </div>
            </div>

            <hr class="fluent-divider">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Department</label>
                    <div class="fluent-input">
                        <select name="department" id="deptSelect">
                            <option value="">— Select —</option>
                            <?php foreach ($departments as $d): ?>
                            <option <?= ($v['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Semester</label>
                    <div class="fluent-input">
                        <select name="semester" id="semSelect">
                            <?php
                            $curDept = $v['department'] ?? '';
                            if ($curDept) {
                                $maxSem = dept_max_semesters($pdo, $curDept);
                                $allSems = array_merge($semBase, $semExtra);
                                $sems = array_slice($allSems, 0, $maxSem);
                                echo '<option value="">— Select —</option>';
                                foreach ($sems as $s) {
                                    $sel = ($v['semester'] ?? '') === $s ? 'selected' : '';
                                    echo "<option $sel>$s</option>";
                                }
                            } else {
                                echo '<option value="">— Select department first —</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Shift pills -->
            <div>
                <label class="fluent-label block mb-1.5">Shift</label>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ($shifts as $sh): ?>
                    <label class="shift-option flex items-center gap-2 px-4 py-2 rounded-md cursor-pointer transition"
                           style="border:1px solid var(--border);font-size:13px;color:var(--text-secondary);"
                           data-value="<?= $sh ?>">
                        <input type="radio" name="shift" value="<?= $sh ?>" class="hidden"
                               <?= ($v['shift'] ?? '') === $sh ? 'checked' : '' ?>>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?= $sh ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Active toggle -->
            <div class="flex items-center gap-3">
                <div class="fluent-toggle <?= $row['status'] ? 'on' : '' ?>" id="statusToggle"></div>
                <label style="font-size:14px;color:var(--text);">Account active</label>
                <input type="checkbox" name="status" id="statusCheck" value="1"
                       <?= $row['status'] ? 'checked' : '' ?> class="hidden">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="fluent-btn-accent fluent-btn">Save Changes</button>
                <a href="index.php" class="fluent-btn">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
const semBase  = <?= json_encode($semBase) ?>;
const semExtra = <?= json_encode($semExtra) ?>;
const deptSel  = document.getElementById('deptSelect');
const semSel   = document.getElementById('semSelect');
const curSem   = <?= json_encode($v['semester'] ?? '') ?>;

const DEPTS = <?= dept_js_map($pdo) ?>;
deptSel.addEventListener('change', function () {
    const maxSem = DEPTS[this.value] ? DEPTS[this.value].max : 4;
    const opts = maxSem > 4 ? semBase.concat(semExtra.slice(0, maxSem - 4)) : semBase.slice(0, maxSem);
    semSel.innerHTML = '<option value="">— Select semester —</option>';
    opts.forEach(function (o) {
        const el = document.createElement('option');
        el.textContent = o;
        if (o === curSem) el.selected = true;
        semSel.appendChild(el);
    });
});

/* Shift pills */
function setShift(val) {
    document.querySelectorAll('.shift-option').forEach(function (el) {
        const active = el.dataset.value === val;
        el.style.background  = active ? 'color-mix(in srgb,#7a3db3 10%,transparent)' : 'transparent';
        el.style.borderColor = active ? '#7a3db3' : 'var(--border)';
        el.style.color       = active ? '#7a3db3' : 'var(--text-secondary)';
        el.style.fontWeight  = active ? '600' : '400';
        el.querySelector('input').checked = active;
    });
}
const initShift = <?= json_encode($v['shift'] ?? '') ?>;
if (initShift) setShift(initShift);
document.querySelectorAll('.shift-option').forEach(function (el) {
    el.addEventListener('click', function () { setShift(this.dataset.value); });
});

/* Generate roll number */
document.getElementById('genRollBtn').addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '…';
    fetch('<?= BASE_URL ?>/admin/students/gen_roll.php')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('rollNoInput').value = data.roll_no;
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + data.roll_no;
            setTimeout(function () {
                btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Generate';
            }, 2000);
        })
        .catch(function () { btn.disabled = false; btn.textContent = 'Generate'; });
});

/* Status toggle */
const toggle = document.getElementById('statusToggle');
const check  = document.getElementById('statusCheck');
toggle.addEventListener('click', function () {
    this.classList.toggle('on');
    check.checked = this.classList.contains('on');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
