<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: index.php'); exit; }

// Resolve teacher record
$teacher = $pdo->prepare(
    'SELECT t.id AS teacher_id, u.name FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.id = ?'
);
$teacher->execute([$userId]);
$teacher = $teacher->fetch();
if (!$teacher) { header('Location: index.php'); exit; }

$teacherId = $teacher['teacher_id'];
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
            $stmt = $pdo->prepare(
                'INSERT INTO teacher_courses (teacher_id, no, subject_name, department, semester, shift, credits)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$teacherId, $no, $subject, $dept ?: null, $sem ?: null, $shift ?: null, $credits]);
            $success = 'Course added successfully.';
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
    <div class="flex justify-between items-center mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">
                Assigned Courses — <?= htmlspecialchars($teacher['name']) ?>
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage subject assignments for this teacher.</p>
        </div>
        <a href="index.php" class="text-sm text-blue-600 hover:underline">← Back to Teachers</a>
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

    <!-- Course list -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
        <table class="w-full text-sm">
            <thead class="bg-blue-900 text-white">
                <tr>
                    <th class="px-4 py-3 text-left font-medium w-16">No.</th>
                    <th class="px-4 py-3 text-left font-medium">Subject Name</th>
                    <th class="px-4 py-3 text-left font-medium">Department</th>
                    <th class="px-4 py-3 text-left font-medium">Semester</th>
                    <th class="px-4 py-3 text-left font-medium">Shift</th>
                    <th class="px-4 py-3 text-left font-medium w-20">Credits</th>
                    <th class="px-4 py-3 text-left font-medium w-20">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($courses)): ?>
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No courses assigned yet.</td>
            </tr>
            <?php endif; ?>
            <?php foreach ($courses as $i => $c): ?>
            <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> border-b border-gray-100 hover:bg-blue-50 transition">
                <td class="px-4 py-3 text-gray-500"><?= (int)$c['no'] ?></td>
                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($c['subject_name']) ?></td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['department'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['semester'] ?? '—') ?></td>
                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['shift'] ?? '—') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full">
                        <?= (int)$c['credits'] ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <form method="POST" onsubmit="return confirm('Remove this course?')">
                        <input type="hidden" name="_action"   value="delete">
                        <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add course form -->
    <div class="bg-white rounded-xl shadow-sm p-6 max-w-2xl">
        <h2 class="text-base font-semibold text-gray-800 mb-4">Assign New Course</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="_action" value="add">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No.</label>
                    <input type="number" name="no" value="<?= count($courses) + 1 ?>" min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name *</label>
                    <input type="text" name="subject_name" required placeholder="e.g. Mathematics"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select name="department" id="deptSelect"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">— Select —</option>
                        <option>Nursing</option>
                        <option>Pharmacy</option>
                        <option>Protiz</option>
                        <option>Technology</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                    <select name="semester" id="semesterSelect"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">— Select —</option>
                        <option>1st Semester</option>
                        <option>2nd Semester</option>
                        <option>3rd Semester</option>
                        <option>4th Semester</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                    <select name="shift"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">— Select —</option>
                        <option>06:00 – 09:00</option>
                        <option>09:00 – 12:00</option>
                        <option>01:00 – 04:00</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Credits (weekly classes)</label>
                    <input type="number" name="credits" value="3" min="1" max="20"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
            </div>

            <button type="submit"
                    class="bg-blue-800 hover:bg-blue-900 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                + Assign Course
            </button>
        </form>
    </div>
</main>

<script>
const dept = document.getElementById('deptSelect');
const sem  = document.getElementById('semesterSelect');
const base = ['1st Semester','2nd Semester','3rd Semester','4th Semester'];
const extra = ['5th Semester','6th Semester'];

dept.addEventListener('change', function () {
    const current = sem.value;
    sem.innerHTML = '<option value="">— Select —</option>';
    const opts = this.value === 'Nursing' ? base.concat(extra) : base;
    opts.forEach(function(o) {
        const el = document.createElement('option');
        el.textContent = o;
        if (o === current) el.selected = true;
        sem.appendChild(el);
    });
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
