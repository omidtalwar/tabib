<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: index.php'); exit; }

$teacher = $pdo->prepare(
    'SELECT t.id AS teacher_id, u.name FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.id = ?'
);
$teacher->execute([$userId]);
$teacher = $teacher->fetch();
if (!$teacher) { header('Location: index.php'); exit; }

$teacherId  = $teacher['teacher_id'];
$pageTitle  = 'Courses — ' . htmlspecialchars($teacher['name']) . ' — ' . SITE_NAME;
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add') {
        $no      = (int)($_POST['no']           ?? 1);
        $subject = trim($_POST['subject_name']  ?? '');
        $dept    = trim($_POST['department']    ?? '');
        $sem     = trim($_POST['semester']      ?? '');
        $shift   = trim($_POST['shift']         ?? '');
        $credits = (int)($_POST['credits']      ?? 1);

        if (!$subject) {
            $error = 'Subject name is required.';
        } else {
            $pdo->prepare(
                'INSERT INTO teacher_courses (teacher_id, no, subject_name, department, semester, shift, credits)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$teacherId, $no, $subject, $dept ?: null, $sem ?: null, $shift ?: null, $credits]);
            $success = 'Course assigned successfully.';
        }

    } elseif ($action === 'delete') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        if ($courseId) {
            $pdo->prepare('DELETE FROM teacher_courses WHERE id = ? AND teacher_id = ?')
                ->execute([$courseId, $teacherId]);
        }
        header("Location: courses.php?id=$userId");
        exit;
    }
}

$courses = $pdo->prepare(
    'SELECT * FROM teacher_courses WHERE teacher_id = ? ORDER BY no, id'
);
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Assigned Courses</h1>
            <p class="fluent-caption mt-1"><?= htmlspecialchars($teacher['name']) ?></p>
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

    <!-- Course list -->
    <div class="fluent-card overflow-hidden mb-6 fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th>Subject Name</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Shift</th>
                    <th style="width:80px;">Credits</th>
                    <th style="width:80px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($courses)): ?>
            <tr>
                <td colspan="7" style="text-align:center;padding:40px 16px;color:var(--text-tertiary);">
                    No courses assigned yet. Use the form below to add one.
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($courses as $c): ?>
            <tr>
                <td style="color:var(--text-tertiary);font-weight:600;"><?= (int)$c['no'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($c['subject_name']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['department'] ?? '—') ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['semester'] ?? '—') ?></td>
                <td>
                    <?php if ($c['shift']): ?>
                    <span class="fluent-badge"><?= htmlspecialchars($c['shift']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <span class="fluent-badge"><?= (int)$c['credits'] ?> cr</span>
                </td>
                <td>
                    <form method="POST" onsubmit="return confirm('Remove this course?')">
                        <input type="hidden" name="_action"   value="delete">
                        <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="fluent-btn"
                                style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                            Remove
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add course form -->
    <div class="fluent-card p-6 max-w-2xl fluent-fade-in" style="animation-delay:100ms;">
        <h2 class="fluent-h3 mb-5">Assign New Course</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="_action" value="add">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">No.</label>
                    <div class="fluent-input">
                        <input type="number" name="no" value="<?= count($courses) + 1 ?>" min="1">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Subject Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="subject_name" required placeholder="e.g. Anatomy">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Department</label>
                    <div class="fluent-input">
                        <select name="department" id="deptSelect">
                            <option value="">— Select —</option>
                            <option>Nursing</option>
                            <option>Pharmacy</option>
                            <option>Protiz</option>
                            <option>Technology</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Semester</label>
                    <div class="fluent-input">
                        <select name="semester" id="semesterSelect">
                            <option value="">— Select —</option>
                            <option>1st Semester</option>
                            <option>2nd Semester</option>
                            <option>3rd Semester</option>
                            <option>4th Semester</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Shift</label>
                    <div class="fluent-input">
                        <select name="shift">
                            <option value="">— Select —</option>
                            <option>06:00 – 09:00</option>
                            <option>09:00 – 12:00</option>
                            <option>01:00 – 04:00</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Credits (weekly classes)</label>
                    <div class="fluent-input">
                        <input type="number" name="credits" value="3" min="1" max="20">
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="fluent-btn-accent fluent-btn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Assign Course
                </button>
            </div>
        </form>
    </div>
</main>

<script>
const dept = document.getElementById('deptSelect');
const sem  = document.getElementById('semesterSelect');
const base  = ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
const extra = ['5th Semester','6th Semester'];
dept.addEventListener('change', function () {
    const current = sem.value;
    sem.innerHTML = '<option value="">— Select —</option>';
    (this.value === 'Nursing' ? base.concat(extra) : base).forEach(function(o) {
        const el = document.createElement('option');
        el.textContent = o;
        if (o === current) el.selected = true;
        sem.appendChild(el);
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
