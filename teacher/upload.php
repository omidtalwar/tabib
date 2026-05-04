<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'Upload Material — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher   = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

$classes = [];
if ($teacherId) {
    $stmt = $pdo->prepare('SELECT id, name FROM classes WHERE teacher_id = ?');
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll();
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $teacherId) {
    $title   = trim($_POST['title']       ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $classId = (int)($_POST['class_id']   ?? 0) ?: null;
    $filePath = null;

    if (!$title) {
        $error = 'Title is required.';
    } elseif (!empty($_FILES['file']['name'])) {
        $allowed = ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = 'File type not allowed.';
        } elseif ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            $error = 'File must be under 10 MB.';
        } else {
            $filename = uniqid('mat_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $filename)) {
                $filePath = $filename;
            } else {
                $error = 'File upload failed.';
            }
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare(
            'INSERT INTO materials (teacher_id, class_id, title, description, file_path) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$teacherId, $classId, $title, $desc, $filePath]);
        $success = 'Material uploaded successfully.';
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">
    <h1 class="text-xl font-bold text-gray-800 mb-5">Upload Study Material</h1>

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

    <div class="bg-white rounded-xl shadow-sm p-6 max-w-lg">
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                <input type="text" name="title" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
            </div>
            <?php if ($classes): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                <select name="class_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
                    <option value="">— All Classes —</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">File (max 10 MB)</label>
                <input type="file" name="file" class="w-full text-sm text-gray-500">
            </div>
            <button type="submit"
                    class="bg-blue-800 hover:bg-blue-900 text-white font-semibold px-6 py-2 rounded-lg text-sm transition">
                Upload
            </button>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
