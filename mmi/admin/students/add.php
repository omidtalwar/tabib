<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Register Student — ' . SITE_NAME;
$error = $success = '';

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = get_departments($pdo);

$shifts = ['06:00 – 09:00','09:00 – 12:00','01:00 – 04:00'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']         ?? '');
    $fatherName  = trim($_POST['father_name']  ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $fatherPhone = trim($_POST['father_phone'] ?? '');
    $password    = trim($_POST['password']     ?? '');
    $rollNo      = trim($_POST['roll_no']      ?? '');
    $dept        = trim($_POST['department']   ?? '');
    $sem         = trim($_POST['semester']     ?? '');
    $shift       = trim($_POST['shift']        ?? '');

    if (!$name || !$password) {
        $error = 'Name and password are required.';
    } else {
        $email = 'student_' . uniqid() . '@mmi.local';
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, "student", ?)')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null]);
            $userId = $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO students (user_id, roll_no, father_name, father_phone, department, semester, shift)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $rollNo ?: null, $fatherName ?: null, $fatherPhone ?: null,
                        $dept ?: null, $sem ?: null, $shift ?: null]);
            $pdo->commit();
            $success = "Student \"$name\" registered successfully.";
            log_activity($pdo, 'student_registered', $name . ' — ' . ($dept ?: 'no dept') . ' sem ' . ($sem ?: '?'));
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Roll number already exists.' : 'Could not register student.';
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
            <h1 class="fluent-h1">Register Student</h1>
            <p class="fluent-caption mt-1">Create a new student account.</p>
        </div>
        <a href="index.php" class="fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success" data-flash>
        <?= htmlspecialchars($success) ?>
        <a href="index.php" style="color:var(--accent);margin-left:8px;">View all students →</a>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="fluent-card p-6 max-w-xl fluent-fade-in" style="animation-delay:60ms;">
        <form method="POST" class="space-y-5">

            <!-- Name row -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Full Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="name" required
                               placeholder="e.g. Ahmad Karimi"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Father's Name</label>
                    <div class="fluent-input">
                        <input type="text" name="father_name"
                               placeholder="e.g. Mohammad Karimi"
                               value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Phone row -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Student Phone
                        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">— optional</span>
                    </label>
                    <div class="fluent-input">
                        <input type="tel" name="phone" placeholder="e.g. 0700000000"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Father's Phone
                        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">— optional</span>
                    </label>
                    <div class="fluent-input">
                        <input type="tel" name="father_phone" placeholder="e.g. 0700000000"
                               value="<?= htmlspecialchars($_POST['father_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Roll No -->
            <div>
                <label class="fluent-label block mb-1.5">Roll No</label>
                <div class="flex gap-2">
                    <div class="fluent-input flex-1">
                        <input type="text" name="roll_no" id="rollNoInput"
                               placeholder="MMI-00001"
                               value="<?= htmlspecialchars($_POST['roll_no'] ?? '') ?>">
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

            <!-- Password -->
            <div>
                <label class="fluent-label block mb-1.5">Password *</label>
                <div class="fluent-input">
                    <input type="password" name="password" required minlength="6" placeholder="Min. 6 characters">
                </div>
            </div>

            <hr class="fluent-divider">

            <!-- Department → Semester cascade -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Department</label>
                    <div class="fluent-input">
                        <select name="department" id="deptSelect">
                            <option value="">— Select —</option>
                            <?php foreach ($allDepts as $d): ?>
                            <option value="<?= htmlspecialchars($d['name_en']) ?>"
                                    <?= ($_POST['department'] ?? '') === $d['name_en'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name_en']) ?>
                                <?php if ($d['name_ps']): ?>(<?= htmlspecialchars($d['name_ps']) ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Semester</label>
                    <div class="fluent-input">
                        <select name="semester" id="semSelect">
                            <option value="">— Select department first —</option>
                            <?php
                            if (!empty($_POST['department'])) {
                                $selDept = $_POST['department'];
                                $maxSem  = 4;
                                foreach ($allDepts as $d) {
                                    if ($d['name_en'] === $selDept) { $maxSem = (int)$d['max_semesters']; break; }
                                }
                                $suffixes = ['','st','nd','rd'];
                                for ($i = 1; $i <= $maxSem; $i++) {
                                    $suf = $i <= 3 ? $suffixes[$i] : 'th';
                                    $lbl = $i . $suf . ' Semester';
                                    echo '<option' . (($_POST['semester'] ?? '') === $lbl ? ' selected' : '') . '>' . $lbl . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Shift -->
            <div>
                <label class="fluent-label block mb-1.5">Shift</label>
                <div class="flex gap-2 flex-wrap" id="shiftGroup">
                    <?php foreach ($shifts as $sh): ?>
                    <label class="shift-option flex items-center gap-2 px-4 py-2 rounded-md cursor-pointer transition"
                           style="border:1px solid var(--border);font-size:13px;color:var(--text-secondary);"
                           data-value="<?= $sh ?>">
                        <input type="radio" name="shift" value="<?= $sh ?>" class="hidden"
                               <?= ($_POST['shift'] ?? '') === $sh ? 'checked' : '' ?>>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?= $sh ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="shift" id="shiftHidden" value="<?= htmlspecialchars($_POST['shift'] ?? '') ?>">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="fluent-btn-accent fluent-btn">Register Student</button>
                <a href="index.php" class="fluent-btn">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
/* Department data from server */
const deptData = <?= json_encode(array_column($allDepts, null, 'name_en')) ?>;

function buildSemesters(maxSem) {
    const suffixes = ['', 'st', 'nd', 'rd'];
    const opts = [];
    for (let i = 1; i <= maxSem; i++) {
        const suf = i <= 3 ? suffixes[i] : 'th';
        opts.push(i + suf + ' Semester');
    }
    return opts;
}

const deptSel = document.getElementById('deptSelect');
const semSel  = document.getElementById('semSelect');

deptSel.addEventListener('change', function () {
    const d    = deptData[this.value];
    const max  = d ? parseInt(d.max_semesters) : 4;
    const opts = buildSemesters(max);
    semSel.innerHTML = '<option value="">— Select semester —</option>';
    opts.forEach(function (o) {
        const el = document.createElement('option');
        el.textContent = o;
        semSel.appendChild(el);
    });
});

/* Shift pill toggle */
function setShift(val) {
    document.querySelectorAll('.shift-option').forEach(function (el) {
        const active = el.dataset.value === val;
        el.style.background  = active ? 'color-mix(in srgb,#7a3db3 10%,transparent)' : 'transparent';
        el.style.borderColor = active ? '#7a3db3' : 'var(--border)';
        el.style.color       = active ? '#7a3db3' : 'var(--text-secondary)';
        el.style.fontWeight  = active ? '600' : '400';
        el.querySelector('input').checked = active;
    });
    document.getElementById('shiftHidden').value = val;
}

const initShift = <?= json_encode($_POST['shift'] ?? '') ?>;
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
