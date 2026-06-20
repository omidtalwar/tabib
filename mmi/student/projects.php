<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shamsi.php';
require_role('student');

$pageTitle = 'My Projects — ' . SITE_NAME;
$user   = current_user();
$error  = $success = '';

// Get student record
$stmt = $pdo->prepare('SELECT * FROM students WHERE user_id = ?');
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

// ── Handle upload ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $title   = trim($_POST['title']       ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject']     ?? '');

    if (!$title) {
        $error = 'Project title is required.';
    } elseif (empty($_FILES['file']['name'])) {
        $error = 'Please attach a file for your project.';
    } else {
        $allowed = ['pdf','doc','docx','ppt','pptx','zip','rar','jpg','jpeg','png'];
        $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = 'File type not allowed. Accepted: PDF, DOC, DOCX, PPT, ZIP, RAR, JPG, PNG.';
        } elseif ($_FILES['file']['size'] > 20 * 1024 * 1024) {
            $error = 'File must be under 20 MB.';
        } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error (code ' . $_FILES['file']['error'] . ').';
        } else {
            $filename = uniqid('proj_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $filename)) {
                $pdo->prepare(
                    'INSERT INTO student_projects (student_id, title, description, subject, file_path)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$student['id'], $title, $desc ?: null, $subject ?: null, $filename]);
                $success = 'Project "' . htmlspecialchars($title) . '" submitted successfully!';
                log_activity($pdo, 'project_submitted', $title . ($subject ? ' — ' . $subject : ''));
            } else {
                $error = 'File move failed. Check server permissions.';
            }
        }
    }
}

// ── Load own projects ────────────────────────────────────────────────────────
$projects = [];
if ($student) {
    $stmt = $pdo->prepare(
        'SELECT * FROM student_projects WHERE student_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$student['id']]);
    $projects = $stmt->fetchAll();
}

$statusColors = [
    'submitted' => ['bg' => '#1b6ec2', 'label' => 'Submitted'],
    'reviewed'  => ['bg' => '#107c10', 'label' => 'Reviewed'],
    'returned'  => ['bg' => '#c42b1c', 'label' => 'Returned'],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">My Projects</h1>
            <p class="fluent-caption mt-1">Submit your academic projects here.</p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger mb-4" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Upload form -->
    <div class="fluent-card p-6 max-w-lg mb-6 fluent-fade-in" style="animation-delay:40ms;">
        <h2 class="fluent-label mb-4" style="font-size:13px;font-weight:700;">SUBMIT NEW PROJECT</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">

            <div>
                <label class="fluent-label block mb-1.5">Project Title *</label>
                <div class="fluent-input">
                    <input type="text" name="title" required placeholder="e.g. Patient Care Research Paper"
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Subject / Course
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">— optional</span>
                </label>
                <div class="fluent-input">
                    <input type="text" name="subject" placeholder="e.g. Nursing 301"
                           value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Description
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">— optional</span>
                </label>
                <div class="fluent-input" style="padding:0;">
                    <textarea name="description" rows="2"
                              style="width:100%;padding:8px 12px;background:transparent;border:none;resize:vertical;outline:none;font-size:13px;"
                              placeholder="Brief description of your project…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Project File *
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-tertiary);font-size:11px;">
                        PDF, DOC, PPT, ZIP, JPG — max 20 MB
                    </span>
                </label>
                <div style="border:2px dashed var(--border);border-radius:8px;padding:20px;text-align:center;cursor:pointer;position:relative;transition:border-color 150ms;"
                     id="dropZone">
                    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p style="font-size:13px;color:var(--text-secondary);">Drag & drop or <span style="color:var(--accent);">browse</span></p>
                    <p id="dropFileName" style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">No file chosen</p>
                    <input type="file" name="file" id="projFile" required
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png"
                           style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
                </div>
            </div>

            <button type="submit" class="fluent-btn-accent fluent-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Submit Project
            </button>
        </form>
    </div>

    <!-- Submitted projects list -->
    <div class="mb-3 fluent-fade-in" style="animation-delay:80ms;">
        <h2 class="fluent-h2">Submitted Projects
            <span class="fluent-badge" style="margin-left:6px;"><?= count($projects) ?></span>
        </h2>
    </div>

    <?php if (empty($projects)): ?>
    <div class="fluent-card p-12 text-center fluent-fade-in" style="animation-delay:90ms;">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No projects submitted yet.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3 fluent-fade-in" style="animation-delay:90ms;">
        <?php foreach ($projects as $p):
            $sc = $statusColors[$p['status']] ?? $statusColors['submitted'];
            $ext = $p['file_path'] ? strtoupper(pathinfo($p['file_path'], PATHINFO_EXTENSION)) : '';
        ?>
        <div class="fluent-card p-4 flex items-start gap-4">
            <!-- Icon -->
            <div style="width:44px;height:44px;border-radius:10px;background:color-mix(in srgb,var(--accent) 10%,transparent);
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <!-- Details -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($p['title']) ?></span>
                    <span style="background:<?= $sc['bg'] ?>;color:white;font-size:10px;font-weight:700;
                                 padding:1px 8px;border-radius:10px;"><?= $sc['label'] ?></span>
                </div>
                <?php if ($p['subject']): ?>
                <p style="font-size:12px;color:var(--accent);margin-top:2px;font-weight:500;">
                    <?= htmlspecialchars($p['subject']) ?>
                </p>
                <?php endif; ?>
                <?php if ($p['description']): ?>
                <p style="font-size:12px;color:var(--text-secondary);margin-top:3px;">
                    <?= htmlspecialchars(mb_substr($p['description'], 0, 120)) ?>
                </p>
                <?php endif; ?>
                <?php if ($p['admin_note']): ?>
                <div style="margin-top:6px;padding:6px 10px;border-radius:6px;background:color-mix(in srgb,#c42b1c 8%,transparent);border:1px solid color-mix(in srgb,#c42b1c 20%,transparent);">
                    <p style="font-size:11px;color:#c42b1c;font-weight:600;">Admin note:</p>
                    <p style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($p['admin_note']) ?></p>
                </div>
                <?php endif; ?>
                <p style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                    Submitted <?= shamsiDate($p['created_at']) ?>
                </p>
            </div>
            <!-- File -->
            <?php if ($p['file_path']): ?>
            <a href="<?= UPLOAD_URL . htmlspecialchars($p['file_path']) ?>" target="_blank"
               class="fluent-btn" style="padding:6px 14px;font-size:12px;flex-shrink:0;">
                <?= $ext ?> ↓
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<script>
var dz   = document.getElementById('dropZone');
var inp  = document.getElementById('projFile');
var lbl  = document.getElementById('dropFileName');
inp.addEventListener('change', function () {
    lbl.textContent = this.files[0] ? this.files[0].name : 'No file chosen';
    dz.style.borderColor = this.files[0] ? 'var(--accent)' : 'var(--border)';
});
['dragover','dragenter'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) { e.preventDefault(); dz.style.borderColor = 'var(--accent)'; });
});
['dragleave','drop'].forEach(function (ev) {
    dz.addEventListener(ev, function (e) {
        e.preventDefault();
        if (ev === 'drop' && e.dataTransfer.files[0]) {
            var dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            inp.files = dt.files;
            lbl.textContent = e.dataTransfer.files[0].name;
            dz.style.borderColor = 'var(--accent)';
        } else {
            dz.style.borderColor = 'var(--border)';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
