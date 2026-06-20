<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Drive — ' . SITE_NAME;
$error = $success = '';

// ── Helpers ─────────────────────────────────────────────────────────────────

function driveDetectCategory(string $ext): string {
    static $map = [
        'pdf'  => 'Documents', 'doc'  => 'Documents', 'docx' => 'Documents',
        'txt'  => 'Documents', 'rtf'  => 'Documents',
        'xls'  => 'Spreadsheets', 'xlsx' => 'Spreadsheets', 'csv' => 'Spreadsheets',
        'ppt'  => 'Presentations', 'pptx' => 'Presentations',
        'jpg'  => 'Images', 'jpeg' => 'Images', 'png' => 'Images',
        'gif'  => 'Images', 'svg'  => 'Images', 'webp' => 'Images', 'bmp' => 'Images',
        'mp4'  => 'Videos', 'avi'  => 'Videos', 'mov' => 'Videos', 'mkv' => 'Videos',
        'zip'  => 'Archives', 'rar'  => 'Archives', '7z' => 'Archives',
        'tar'  => 'Archives', 'gz'   => 'Archives',
    ];
    return $map[strtolower($ext)] ?? 'Other';
}

function driveHumanSize(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 1) . ' GB';
}

function driveFolderPath(PDO $pdo, int $id): array {
    $path = []; $visited = [];
    while ($id > 0 && !in_array($id, $visited)) {
        $visited[] = $id;
        $s = $pdo->prepare('SELECT id, name, parent_id FROM drive_folders WHERE id = ?');
        $s->execute([$id]);
        $f = $s->fetch();
        if (!$f) break;
        array_unshift($path, $f);
        $id = (int)($f['parent_id'] ?? 0);
    }
    return $path;
}

function driveDeletePhysical(PDO $pdo, int $folderId): void {
    $s = $pdo->prepare('SELECT file_path FROM drive_files WHERE folder_id = ?');
    $s->execute([$folderId]);
    foreach ($s->fetchAll() as $f) {
        $p = UPLOAD_DIR . $f['file_path'];
        if (file_exists($p)) unlink($p);
    }
    $s = $pdo->prepare('SELECT id FROM drive_folders WHERE parent_id = ?');
    $s->execute([$folderId]);
    foreach ($s->fetchAll() as $sub) {
        driveDeletePhysical($pdo, (int)$sub['id']);
    }
}

function driveFolderSelectList(PDO $pdo, ?int $exclude = null): array {
    $all = $pdo->query('SELECT id, parent_id, name FROM drive_folders ORDER BY name')->fetchAll();
    $result = [];
    function buildList(array $all, ?int $parentId, int $depth, ?int $exclude, array &$result): void {
        foreach ($all as $f) {
            if ((int)($f['parent_id'] ?? 0) !== (int)($parentId ?? 0)) continue;
            if ($f['id'] === $exclude) continue;
            $result[] = ['id' => $f['id'], 'label' => str_repeat('  ', $depth) . $f['name']];
            buildList($all, $f['id'], $depth + 1, $exclude, $result);
        }
    }
    buildList($all, null, 0, $exclude, $result);
    return $result;
}

$categories = ['Documents', 'Spreadsheets', 'Presentations', 'Images', 'Videos', 'Archives', 'Other'];
$catStyle   = [
    'Documents'     => ['#0f6cbd', 'rgba(15,108,189,.12)'],
    'Spreadsheets'  => ['#0e7a0e', 'rgba(14,122,14,.12)'],
    'Presentations' => ['#c2500f', 'rgba(194,80,15,.12)'],
    'Images'        => ['#7a3db3', 'rgba(122,61,179,.12)'],
    'Videos'        => ['#c42b1c', 'rgba(196,43,28,.12)'],
    'Archives'      => ['#8a6f00', 'rgba(138,111,0,.12)'],
    'Other'         => ['#5a5a72', 'rgba(90,90,114,.12)'],
];

$allowed = ['pdf','doc','docx','xls','xlsx','csv','ppt','pptx',
            'jpg','jpeg','png','gif','svg','webp','bmp',
            'mp4','avi','mov','mkv',
            'zip','rar','7z','tar','gz','txt','rtf'];

// ── State ────────────────────────────────────────────────────────────────────
$folderId = (int)($_GET['folder_id'] ?? 0);
$q        = trim($_GET['q'] ?? '');
$catFilt  = trim($_GET['cat'] ?? '');

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_folder') {
        $fname    = trim($_POST['folder_name'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($fname) {
            $pdo->prepare('INSERT INTO drive_folders (parent_id, name, created_by) VALUES (?, ?, ?)')
                ->execute([$parentId, $fname, $_SESSION['user_id']]);
            $success  = "Folder \"$fname\" created.";
            log_activity($pdo, 'drive_folder_created', $fname);
        } else {
            $error = 'Folder name cannot be empty.';
        }
        $folderId = (int)($_POST['parent_id'] ?? 0);

    } elseif ($action === 'rename_folder') {
        $renId   = (int)($_POST['rename_id'] ?? 0);
        $newName = trim($_POST['new_name'] ?? '');
        if ($renId && $newName) {
            $pdo->prepare('UPDATE drive_folders SET name = ? WHERE id = ?')->execute([$newName, $renId]);
            $success = 'Folder renamed.';
        }
        $folderId = (int)($_POST['current_folder'] ?? 0);

    } elseif ($action === 'rename_file') {
        $renId   = (int)($_POST['rename_id'] ?? 0);
        $newName = trim($_POST['new_name'] ?? '');
        if ($renId && $newName) {
            $pdo->prepare('UPDATE drive_files SET name = ? WHERE id = ?')->execute([$newName, $renId]);
            $success = 'File renamed.';
        }
        $folderId = (int)($_POST['current_folder'] ?? 0);

    } elseif ($action === 'upload') {
        $displayName  = trim($_POST['file_title'] ?? '');
        $uploadFolder = (int)($_POST['upload_folder_id'] ?? 0) ?: null;
        if (empty($_FILES['file']['name'])) {
            $error = 'No file selected.';
        } else {
            $origName = $_FILES['file']['name'];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $error = 'File type .' . htmlspecialchars($ext) . ' is not allowed.';
            } elseif ($_FILES['file']['size'] > 50 * 1024 * 1024) {
                $error = 'File must be under 50 MB.';
            } else {
                if (!$displayName) $displayName = pathinfo($origName, PATHINFO_FILENAME);
                $stored    = 'drive_' . uniqid('', true) . '.' . $ext;
                $uploadDir = UPLOAD_DIR . 'drive/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $stored)) {
                    $cat = driveDetectCategory($ext);
                    $pdo->prepare(
                        'INSERT INTO drive_files
                         (folder_id, name, original_name, file_path, file_size, extension, category, uploaded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$uploadFolder, $displayName, $origName,
                                'drive/' . $stored, (int)$_FILES['file']['size'],
                                $ext, $cat, $_SESSION['user_id']]);
                    $success = "\"$displayName\" uploaded.";
                    log_activity($pdo, 'drive_file_uploaded', $displayName . '.' . $ext);
                } else {
                    $error = 'Upload failed — check server permissions on /uploads/drive/.';
                }
            }
        }
        $folderId = (int)($_POST['current_folder'] ?? 0);

    } elseif ($action === 'move_file') {
        $fileId   = (int)($_POST['file_id'] ?? 0);
        $targetId = (int)($_POST['target_folder_id'] ?? 0) ?: null;
        if ($fileId) {
            $pdo->prepare('UPDATE drive_files SET folder_id = ? WHERE id = ?')
                ->execute([$targetId, $fileId]);
            $success = 'File moved.';
        }
        $folderId = (int)($_POST['current_folder'] ?? 0);

    } elseif ($action === 'delete_file') {
        $fileId = (int)($_POST['file_id'] ?? 0);
        $stmt   = $pdo->prepare('SELECT file_path, name FROM drive_files WHERE id = ?');
        $stmt->execute([$fileId]);
        $f = $stmt->fetch();
        if ($f) {
            $p = UPLOAD_DIR . $f['file_path'];
            if (file_exists($p)) unlink($p);
            $pdo->prepare('DELETE FROM drive_files WHERE id = ?')->execute([$fileId]);
            $success = "\"" . htmlspecialchars($f['name']) . "\" deleted.";
            log_activity($pdo, 'drive_file_deleted', $f['name']);
        }
        $folderId = (int)($_POST['current_folder'] ?? 0);

    } elseif ($action === 'delete_folder') {
        $delId    = (int)($_POST['folder_del_id'] ?? 0);
        $parentId = (int)($_POST['parent_folder'] ?? 0);
        if ($delId) {
            driveDeletePhysical($pdo, $delId);
            $pdo->prepare('DELETE FROM drive_folders WHERE id = ?')->execute([$delId]);
            $success = 'Folder and all its contents deleted.';
            log_activity($pdo, 'drive_folder_deleted', 'Folder #' . $delId);
        }
        $folderId = $parentId;
    }
}

// ── Data ─────────────────────────────────────────────────────────────────────
$breadcrumb = $folderId ? driveFolderPath($pdo, $folderId) : [];
$folderSelectList = driveFolderSelectList($pdo);

// Subfolders (skip during search)
$subFolders = [];
if (!$q) {
    $s = $pdo->prepare(
        'SELECT f.*,
                (SELECT COUNT(*) FROM drive_folders sf WHERE sf.parent_id = f.id) +
                (SELECT COUNT(*) FROM drive_files   ff WHERE ff.folder_id  = f.id) AS item_count
         FROM drive_folders f
         WHERE f.parent_id ' . ($folderId ? '= ?' : 'IS NULL') . '
         ORDER BY f.name ASC'
    );
    $s->execute($folderId ? [$folderId] : []);
    $subFolders = $s->fetchAll();
}

// Files
if ($q) {
    $sql    = 'SELECT f.*, u.name AS uploader FROM drive_files f
               LEFT JOIN users u ON u.id = f.uploaded_by
               WHERE f.name LIKE ?';
    $params = ['%' . $q . '%'];
    if ($catFilt) { $sql .= ' AND f.category = ?'; $params[] = $catFilt; }
} else {
    $sql    = 'SELECT f.*, u.name AS uploader FROM drive_files f
               LEFT JOIN users u ON u.id = f.uploaded_by
               WHERE f.folder_id ' . ($folderId ? '= ?' : 'IS NULL');
    $params = $folderId ? [$folderId] : [];
    if ($catFilt) { $sql .= ' AND f.category = ?'; $params[] = $catFilt; }
}
$sql .= ' ORDER BY f.name ASC';
$s = $pdo->prepare($sql);
$s->execute($params);
$files = $s->fetchAll();

// Stats
try {
    $totalFiles   = (int)$pdo->query('SELECT COUNT(*) FROM drive_files')->fetchColumn();
    $totalSize    = (int)$pdo->query('SELECT COALESCE(SUM(file_size),0) FROM drive_files')->fetchColumn();
    $totalFolders = (int)$pdo->query('SELECT COUNT(*) FROM drive_folders')->fetchColumn();
} catch (Exception $e) { $totalFiles = $totalSize = $totalFolders = 0; }

$currentParentForForm = $folderId;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

<style>
.drv-folder-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 14px 12px 10px; cursor: default; transition: all .15s ease; display: flex;
    flex-direction: column; gap: 6px; min-height: 110px; position: relative;
}
.drv-folder-card:hover { border-color: #febc2e; box-shadow: 0 0 0 3px rgba(254,188,46,.12); transform: translateY(-1px); }
.drv-file-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 12px; display: flex; flex-direction: column; gap: 6px;
    min-height: 130px; position: relative; transition: all .15s ease;
}
.drv-file-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.drv-actions { display: flex; gap: 4px; opacity: 0; transition: opacity .15s; flex-wrap: wrap; }
.drv-folder-card:hover .drv-actions,
.drv-file-card:hover .drv-actions { opacity: 1; }
.drv-ext {
    font-size: 9px; font-weight: 800; letter-spacing: .06em; padding: 2px 6px;
    border-radius: 4px; display: inline-block; text-transform: uppercase; font-family: monospace;
}
.cat-pill {
    padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 600;
    border: 1px solid var(--border); background: transparent; color: var(--text-secondary);
    cursor: pointer; text-decoration: none; display: inline-block; white-space: nowrap;
    transition: all .15s;
}
.cat-pill:hover { background: var(--surface-hover); }
.cat-pill.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.drv-modal {
    display: none; position: fixed; inset: 0; z-index: 9990;
    background: rgba(0,0,0,.5); align-items: center; justify-content: center;
}
.drv-modal-box {
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
    padding: 24px; width: 100%; box-shadow: var(--shadow-lg);
}
.drv-input {
    background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    padding: 7px 10px; font-size: 13px; color: var(--text); width: 100%;
    transition: border-color .15s;
}
.drv-input:focus { outline: none; border-color: var(--accent); }
.drv-select {
    background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    padding: 7px 10px; font-size: 13px; color: var(--text); width: 100%;
}
.drv-section-label {
    font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: var(--text-tertiary); margin-bottom: 10px; display: block;
}
</style>

<!-- ── Header ──────────────────────────────────────────────────────────────── -->
<div class="mb-5 fluent-fade-in">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="fluent-h1">Drive</h1>
            <p class="fluent-caption mt-0.5">
                <?= $totalFolders ?> folder<?= $totalFolders != 1 ? 's' : '' ?> &middot;
                <?= $totalFiles ?>  file<?= $totalFiles != 1 ? 's' : '' ?> &middot;
                <?= driveHumanSize($totalSize) ?> used
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Search -->
            <form method="GET" style="display:flex;align-items:center;gap:6px;">
                <?php if ($catFilt): ?>
                <input type="hidden" name="cat" value="<?= htmlspecialchars($catFilt) ?>">
                <?php endif; ?>
                <div class="fluent-input" style="width:220px;">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search all files…" autocomplete="off">
                </div>
                <?php if ($q): ?>
                <a href="<?= BASE_URL ?>/admin/drive/<?= $folderId ? '?folder_id='.$folderId : '' ?>" class="fluent-btn" style="padding:7px 10px;" title="Clear search">✕</a>
                <?php endif; ?>
            </form>
            <button onclick="openModal('modalNewFolder')" class="fluent-btn" style="gap:6px;white-space:nowrap;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                New Folder
            </button>
            <button onclick="openModal('modalUpload')" class="fluent-btn-accent fluent-btn" style="gap:6px;white-space:nowrap;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload
            </button>
        </div>
    </div>
</div>

<!-- Alert -->
<?php if ($success): ?>
<div class="fluent-alert mb-4" style="background:rgba(14,122,14,.08);border-color:#0e7a0e;color:#0e7a0e;" id="driveAlert">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    <?= htmlspecialchars($success) ?>
</div>
<?php elseif ($error): ?>
<div class="fluent-alert fluent-alert-danger mb-4" id="driveAlert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Breadcrumbs -->
<nav class="flex items-center gap-1 mb-4 flex-wrap" style="font-size:13px;">
    <a href="<?= BASE_URL ?>/admin/drive/" style="color:var(--accent);text-decoration:none;display:flex;align-items:center;gap:4px;font-weight:500;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
        </svg>
        My Drive
    </a>
    <?php foreach ($breadcrumb as $bc): ?>
    <span style="color:var(--text-tertiary);padding:0 2px;">/</span>
    <a href="<?= BASE_URL ?>/admin/drive/?folder_id=<?= $bc['id'] ?>"
       style="color:var(--accent);text-decoration:none;font-weight:500;">
        <?= htmlspecialchars($bc['name']) ?>
    </a>
    <?php endforeach; ?>
    <?php if ($q): ?>
    <span style="color:var(--text-tertiary);padding:0 2px;">/</span>
    <span style="color:var(--text-secondary);">Search: "<?= htmlspecialchars($q) ?>"</span>
    <?php endif; ?>
</nav>

<!-- Category Filter -->
<div class="flex items-center gap-2 mb-5 flex-wrap">
    <?php
    $baseHref = BASE_URL . '/admin/drive/' . ($folderId && !$q ? '?folder_id='.$folderId : '?') . ($q ? '&q='.urlencode($q) : '');
    ?>
    <a href="<?= BASE_URL ?>/admin/drive/<?= $folderId && !$q ? '?folder_id='.$folderId : '' ?><?= $q ? '?q='.urlencode($q) : '' ?>"
       class="cat-pill <?= !$catFilt ? 'active' : '' ?>">All types</a>
    <?php foreach ($categories as $cat):
        [$fg] = $catStyle[$cat];
        $href = $baseHref . '&cat=' . urlencode($cat);
        $isActive = $catFilt === $cat;
    ?>
    <a href="<?= $href ?>"
       class="cat-pill <?= $isActive ? 'active' : '' ?>"
       style="<?= $isActive ? '' : 'color:'.$fg.';border-color:color-mix(in srgb,'.$fg.' 30%,var(--border));' ?>">
        <?= $cat ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Folders ───────────────────────────────────────────────────────────────── -->
<?php if (!empty($subFolders)): ?>
<div class="mb-6">
    <span class="drv-section-label">Folders</span>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($subFolders as $sf): ?>
        <div class="drv-folder-card">
            <a href="<?= BASE_URL ?>/admin/drive/?folder_id=<?= $sf['id'] ?>" style="text-decoration:none;display:flex;flex-direction:column;gap:6px;flex:1;">
                <svg class="w-9 h-9" viewBox="0 0 24 24" fill="rgba(254,188,46,0.18)" stroke="#febc2e">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                          d="M3 7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                <p style="font-size:12px;font-weight:600;color:var(--text);line-height:1.3;word-break:break-word;">
                    <?= htmlspecialchars($sf['name']) ?>
                </p>
                <p style="font-size:10px;color:var(--text-tertiary);">
                    <?= (int)$sf['item_count'] ?> item<?= $sf['item_count'] != 1 ? 's' : '' ?>
                </p>
            </a>
            <div class="drv-actions">
                <button onclick="openRenameModal('folder',<?= $sf['id'] ?>,<?= $folderId ?>,<?= json_encode($sf['name']) ?>)"
                        class="fluent-btn" style="padding:2px 7px;font-size:10px;">Rename</button>
                <form method="POST" onsubmit="return confirm('Delete «<?= htmlspecialchars(addslashes($sf['name'])) ?>» and all its contents?')">
                    <input type="hidden" name="action" value="delete_folder">
                    <input type="hidden" name="folder_del_id" value="<?= $sf['id'] ?>">
                    <input type="hidden" name="parent_folder" value="<?= $folderId ?>">
                    <button type="submit" class="fluent-btn" style="padding:2px 7px;font-size:10px;color:#c42b1c;border-color:rgba(196,43,28,.4);">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Files ─────────────────────────────────────────────────────────────────── -->
<?php if (!empty($files)): ?>
<div>
    <span class="drv-section-label">
        Files
        <?php if ($q): ?><span style="font-weight:400;text-transform:none;letter-spacing:0;">— <?= count($files) ?> result<?= count($files)!=1?'s':'' ?> for "<?= htmlspecialchars($q) ?>"</span><?php endif; ?>
    </span>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($files as $file):
            [$fg, $bg] = $catStyle[$file['category']] ?? $catStyle['Other'];
        ?>
        <div class="drv-file-card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:4px;">
                <span class="drv-ext" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= htmlspecialchars($file['extension'] ?? '?') ?></span>
                <span style="font-size:10px;color:var(--text-tertiary);flex-shrink:0;"><?= driveHumanSize((int)$file['file_size']) ?></span>
            </div>
            <div style="flex:1;min-height:0;">
                <p style="font-size:12px;font-weight:600;color:var(--text);line-height:1.3;word-break:break-word;margin-bottom:2px;">
                    <?= htmlspecialchars($file['name']) ?>
                </p>
                <p style="font-size:10px;color:var(--text-tertiary);">
                    <?= date('M j, Y', strtotime($file['created_at'])) ?>
                    <?php if ($file['uploader']): ?>&middot; <?= htmlspecialchars($file['uploader']) ?><?php endif; ?>
                </p>
                <?php if ($q && $file['folder_id']): ?>
                <p style="font-size:10px;color:var(--accent);margin-top:2px;">
                    <?php $fp = driveFolderPath($pdo, (int)$file['folder_id']);
                          echo htmlspecialchars(implode(' › ', array_column($fp, 'name'))); ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="drv-actions">
                <a href="<?= BASE_URL ?>/admin/drive/download.php?id=<?= $file['id'] ?>"
                   class="fluent-btn" style="padding:2px 8px;font-size:10px;flex:1;text-align:center;text-decoration:none;">⬇ Download</a>
                <button onclick="openMoveModal(<?= $file['id'] ?>, <?= $folderId ?>, <?= json_encode($file['name']) ?>)"
                        class="fluent-btn" style="padding:2px 7px;font-size:10px;" title="Move">⇄</button>
                <button onclick="openRenameModal('file',<?= $file['id'] ?>,<?= $folderId ?>,<?= json_encode($file['name']) ?>)"
                        class="fluent-btn" style="padding:2px 7px;font-size:10px;" title="Rename">✎</button>
                <form method="POST" onsubmit="return confirm('Delete «<?= htmlspecialchars(addslashes($file['name'])) ?>»?')">
                    <input type="hidden" name="action" value="delete_file">
                    <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                    <input type="hidden" name="current_folder" value="<?= $folderId ?>">
                    <button type="submit" class="fluent-btn" style="padding:2px 7px;font-size:10px;color:#c42b1c;border-color:rgba(196,43,28,.4);">✕</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif (empty($subFolders)): ?>
<!-- Empty state -->
<div class="fluent-card p-12 text-center" style="border-style:dashed;">
    <svg class="w-14 h-14 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.3"
              d="M3 7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
    </svg>
    <p class="fluent-h3 mb-2"><?= $q ? 'No results for "' . htmlspecialchars($q) . '"' : 'Nothing here yet' ?></p>
    <p class="fluent-caption">
        <?= $q ? 'Try a different keyword or clear the filter.' : 'Create a folder or upload files to get started.' ?>
    </p>
    <?php if (!$q): ?>
    <div class="flex items-center justify-center gap-3 mt-5">
        <button onclick="openModal('modalNewFolder')" class="fluent-btn" style="gap:5px;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            New Folder
        </button>
        <button onclick="openModal('modalUpload')" class="fluent-btn-accent fluent-btn" style="gap:5px;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Upload File
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>


<!-- ══ MODALS ══════════════════════════════════════════════════════════════════ -->

<!-- New Folder -->
<div class="drv-modal" id="modalNewFolder">
    <div class="drv-modal-box" style="max-width:380px;">
        <h3 class="fluent-h3 mb-4" style="display:flex;align-items:center;gap:8px;">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#febc2e;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            New Folder
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_folder">
            <input type="hidden" name="parent_id" value="<?= $folderId ?>">
            <div class="mb-2">
                <label class="fluent-label block mb-1.5">Folder name</label>
                <input type="text" name="folder_name" class="drv-input" placeholder="e.g. Invoices 2024" required autofocus>
            </div>
            <?php if ($breadcrumb): ?>
            <p class="fluent-caption mb-4">Will be created inside: <?= htmlspecialchars(end($breadcrumb)['name']) ?></p>
            <?php else: ?>
            <p class="fluent-caption mb-4">Will be created in My Drive root.</p>
            <?php endif; ?>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modalNewFolder')" class="fluent-btn">Cancel</button>
                <button type="submit" class="fluent-btn-accent fluent-btn">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload File -->
<div class="drv-modal" id="modalUpload">
    <div class="drv-modal-box" style="max-width:460px;">
        <h3 class="fluent-h3 mb-4" style="display:flex;align-items:center;gap:8px;">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Upload File
        </h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="current_folder" value="<?= $folderId ?>">
            <!-- Drop zone -->
            <label id="dropZone" for="fileInput"
                   style="display:block;border:2px dashed var(--border);border-radius:8px;padding:22px;
                          text-align:center;cursor:pointer;margin-bottom:14px;transition:border-color .2s;"
                   ondragover="event.preventDefault();this.style.borderColor='var(--accent)'"
                   ondragleave="this.style.borderColor='var(--border)'"
                   ondrop="handleDrop(event)">
                <svg id="dropIcon" class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <p style="font-size:13px;color:var(--text-secondary);">
                    Drag & drop or <span style="color:var(--accent);font-weight:600;">click to browse</span>
                </p>
                <p id="dropFileName" style="font-size:12px;color:var(--accent);font-weight:600;margin-top:4px;min-height:16px;"></p>
                <p style="font-size:10px;color:var(--text-tertiary);margin-top:4px;">
                    PDF, DOC, XLS, PPT, Images, ZIP &middot; Max 50 MB
                </p>
            </label>
            <input type="file" id="fileInput" name="file" style="display:none;"
                   onchange="onFileSelected(this)">

            <div class="mb-3">
                <label class="fluent-label block mb-1">Display name <span style="font-weight:400;color:var(--text-tertiary);">(optional)</span></label>
                <input type="text" name="file_title" class="drv-input" placeholder="Leave blank to use filename">
            </div>
            <div class="mb-4">
                <label class="fluent-label block mb-1">Destination folder</label>
                <select name="upload_folder_id" class="drv-select">
                    <option value="0" <?= !$folderId ? 'selected' : '' ?>>— My Drive (root) —</option>
                    <?php foreach ($folderSelectList as $fl): ?>
                    <option value="<?= $fl['id'] ?>" <?= $fl['id'] == $folderId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fl['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modalUpload')" class="fluent-btn">Cancel</button>
                <button type="submit" class="fluent-btn-accent fluent-btn" style="gap:6px;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div class="drv-modal" id="modalRename">
    <div class="drv-modal-box" style="max-width:360px;">
        <h3 class="fluent-h3 mb-4">Rename</h3>
        <form method="POST" id="renameForm">
            <input type="hidden" name="action" id="renameAction">
            <input type="hidden" name="rename_id" id="renameId">
            <input type="hidden" name="current_folder" id="renameCurrFolder">
            <div class="mb-4">
                <label class="fluent-label block mb-1.5">New name</label>
                <input type="text" name="new_name" id="renameInput" class="drv-input" required>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modalRename')" class="fluent-btn">Cancel</button>
                <button type="submit" class="fluent-btn-accent fluent-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Move File Modal -->
<div class="drv-modal" id="modalMove">
    <div class="drv-modal-box" style="max-width:380px;">
        <h3 class="fluent-h3 mb-4">Move File</h3>
        <form method="POST" id="moveForm">
            <input type="hidden" name="action" value="move_file">
            <input type="hidden" name="file_id" id="moveFileId">
            <input type="hidden" name="current_folder" id="moveCurrFolder">
            <p class="fluent-caption mb-3">Moving: <strong id="moveFileName"></strong></p>
            <div class="mb-4">
                <label class="fluent-label block mb-1.5">Move to folder</label>
                <select name="target_folder_id" class="drv-select">
                    <option value="0">— My Drive (root) —</option>
                    <?php foreach ($folderSelectList as $fl): ?>
                    <option value="<?= $fl['id'] ?>"><?= htmlspecialchars($fl['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeModal('modalMove')" class="fluent-btn">Cancel</button>
                <button type="submit" class="fluent-btn-accent fluent-btn">Move</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Close on backdrop click
document.querySelectorAll('.drv-modal').forEach(function(m) {
    m.addEventListener('click', function(e) { if (e.target === m) closeModal(m.id); });
});

// Drag & drop
function handleDrop(e) {
    e.preventDefault();
    var files = e.dataTransfer.files;
    if (!files.length) return;
    var dt = new DataTransfer();
    dt.items.add(files[0]);
    document.getElementById('fileInput').files = dt.files;
    document.getElementById('dropFileName').textContent = files[0].name;
    document.getElementById('dropZone').style.borderColor = 'var(--accent)';
}
function onFileSelected(input) {
    if (input.files && input.files[0]) {
        document.getElementById('dropFileName').textContent = input.files[0].name;
        document.getElementById('dropZone').style.borderColor = 'var(--accent)';
    }
}

// Rename modal
function openRenameModal(type, id, currFolder, currentName) {
    document.getElementById('renameAction').value  = 'rename_' + type;
    document.getElementById('renameId').value       = id;
    document.getElementById('renameCurrFolder').value = currFolder;
    document.getElementById('renameInput').value    = currentName;
    openModal('modalRename');
    setTimeout(function() { document.getElementById('renameInput').select(); }, 50);
}

// Move modal
function openMoveModal(fileId, currFolder, fileName) {
    document.getElementById('moveFileId').value    = fileId;
    document.getElementById('moveCurrFolder').value = currFolder;
    document.getElementById('moveFileName').textContent = fileName;
    openModal('modalMove');
}

// Auto-dismiss alert
<?php if ($success || $error): ?>
setTimeout(function() {
    var a = document.getElementById('driveAlert');
    if (a) { a.style.transition = 'opacity .6s'; a.style.opacity = '0'; setTimeout(function(){a.remove();},600); }
}, 4000);
<?php endif; ?>
</script>

</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
