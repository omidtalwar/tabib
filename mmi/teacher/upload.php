<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'Materials — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher   = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

// Get teacher's courses for linking
$courses = [];
if ($teacherId) {
    $stmt = $pdo->prepare(
        'SELECT id, subject_name, department, semester, shift
         FROM teacher_courses WHERE teacher_id=? ORDER BY department, semester, subject_name'
    );
    $stmt->execute([$teacherId]);
    $courses = $stmt->fetchAll();
}

$success = $error = '';

// Delete a material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && $teacherId) {
    $delId = (int)$_POST['delete_id'];
    $stmt  = $pdo->prepare('SELECT file_path FROM materials WHERE id=? AND teacher_id=?');
    $stmt->execute([$delId, $teacherId]);
    $mat = $stmt->fetch();
    if ($mat) {
        if ($mat['file_path'] && file_exists(UPLOAD_DIR . $mat['file_path'])) {
            unlink(UPLOAD_DIR . $mat['file_path']);
        }
        $pdo->prepare('DELETE FROM materials WHERE id=? AND teacher_id=?')->execute([$delId, $teacherId]);
        $success = 'Material deleted.';
    }
}

// Upload a new material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && $teacherId) {
    $title    = trim($_POST['title']       ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $courseId = (int)($_POST['course_id']  ?? 0) ?: null;
    $filePath = null;

    if (!$title) {
        $error = 'Title is required.';
    } elseif (!empty($_FILES['file']['name'])) {
        $allowed = ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png'];
        $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = 'File type not allowed. Accepted: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG.';
        } elseif ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            $error = 'File must be under 10 MB.';
        } else {
            $filename = uniqid('mat_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $filename)) {
                $filePath = $filename;
            } else {
                $error = 'File upload failed. Check server permissions.';
            }
        }
    }

    if (!$error) {
        $dueDate = trim($_POST['due_date'] ?? '') ?: null;
        $pdo->prepare(
            'INSERT INTO materials (teacher_id, course_id, title, description, due_date, file_path) VALUES (?,?,?,?,?,?)'
        )->execute([$teacherId, $courseId, $title, $desc, $dueDate, $filePath]);
        $success = 'Material uploaded successfully.';
        log_activity($pdo, 'material_uploaded', $title . ($filePath ? ' (' . $ext . ')' : ' (no file)'));
    }
}

// Load teacher's uploaded materials
$myMaterials = [];
if ($teacherId) {
    $stmt = $pdo->prepare(
        'SELECT m.id, m.title, m.description, m.file_path, m.created_at,
                tc.subject_name, tc.department, tc.semester, tc.shift
         FROM materials m
         LEFT JOIN teacher_courses tc ON tc.id = m.course_id
         WHERE m.teacher_id = ?
         ORDER BY m.created_at DESC'
    );
    $stmt->execute([$teacherId]);
    $myMaterials = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Study Materials</h1>
        <p class="fluent-caption mt-1">Upload materials for your courses. Students see only materials linked to their class.</p>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger mb-4" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Upload form -->
    <div class="fluent-card p-6 max-w-lg mb-6 fluent-fade-in" style="animation-delay:40ms;">
        <h2 class="fluent-label mb-4" style="font-size:13px;font-weight:700;">UPLOAD NEW MATERIAL</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">

            <div>
                <label class="fluent-label block mb-1.5">Title *</label>
                <div class="fluent-input">
                    <input type="text" name="title" required placeholder="e.g. Chapter 3 Notes">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Description</label>
                <div class="fluent-input" style="padding:0;">
                    <textarea name="description" rows="2"
                              style="width:100%;padding:8px 12px;background:transparent;border:none;resize:vertical;outline:none;font-size:13px;"
                              placeholder="Optional notes…"></textarea>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Course
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">
                        — leave blank for general access
                    </span>
                </label>
                <div class="fluent-input">
                    <select name="course_id">
                        <option value="">— General (visible to all students) —</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['subject_name']) ?>
                            — <?= htmlspecialchars($c['department']) ?> / <?= htmlspecialchars($c['semester']) ?> / <?= htmlspecialchars($c['shift']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Closing Date
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">— deadline for students, optional</span>
                </label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <input type="date" name="due_date">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">File
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">
                        — PDF, DOC, PPT, JPG, PNG · max 10 MB
                    </span>
                </label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                       style="font-size:13px;color:var(--text-secondary);">
            </div>

            <button type="submit" class="fluent-btn-accent fluent-btn">Upload Material</button>
        </form>
    </div>

    <!-- Uploaded materials list -->
    <div class="mb-3 fluent-fade-in" style="animation-delay:80ms;">
        <h2 class="fluent-h2">My Uploads
            <span class="fluent-badge" style="margin-left:6px;"><?= count($myMaterials) ?></span>
        </h2>
    </div>

    <?php if (empty($myMaterials)): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in" style="animation-delay:90ms;">
        <p class="fluent-body" style="color:var(--text-tertiary);">No materials uploaded yet.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:90ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Course</th>
                    <th>File</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($myMaterials as $m): ?>
            <tr>
                <td>
                    <p style="font-weight:600;"><?= htmlspecialchars($m['title']) ?></p>
                    <?php if ($m['description']): ?>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-top:1px;">
                        <?= htmlspecialchars(mb_substr($m['description'], 0, 60)) . (mb_strlen($m['description']) > 60 ? '…' : '') ?>
                    </p>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['subject_name']): ?>
                    <span style="font-size:12px;font-weight:600;"><?= htmlspecialchars($m['subject_name']) ?></span><br>
                    <span style="font-size:11px;color:var(--text-tertiary);">
                        <?= htmlspecialchars($m['department'] . ' / ' . $m['semester']) ?>
                    </span>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-tertiary);">General</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['file_path']): ?>
                    <?php $ext = strtoupper(pathinfo($m['file_path'], PATHINFO_EXTENSION)); ?>
                    <a href="<?= UPLOAD_URL . htmlspecialchars($m['file_path']) ?>" target="_blank"
                       class="fluent-badge" style="text-decoration:none;"><?= $ext ?></a>
                    <?php else: ?>
                    <span style="color:var(--text-tertiary);font-size:12px;">No file</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-tertiary);font-size:12px;">
                    <?= date('d M Y', strtotime($m['created_at'])) ?>
                </td>
                <td>
                    <form method="POST"
                          onsubmit="return confirm('Delete this material?')">
                        <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="fluent-btn"
                                style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
