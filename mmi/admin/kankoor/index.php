<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Kankoor Waitlist — ' . SITE_NAME;
$error = $success = '';
$currentYear = (int)date('Y');

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = get_departments($pdo);

// Build dept→years map for JS
$deptYears = [];
foreach ($allDepts as $d) {
    $deptYears[$d['name_en']] = (int)ceil($d['max_semesters'] / 2);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action']       ?? '';
    $id          = (int)($_POST['id']     ?? 0);
    $name        = trim($_POST['name']         ?? '');
    $fatherName  = trim($_POST['father_name']  ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $fatherPhone = trim($_POST['father_phone'] ?? '');
    $dept        = trim($_POST['department']   ?? '');
    $yearReg     = (int)($_POST['year_register']   ?? $currentYear);
    $yearGrad    = (int)($_POST['year_graduation']  ?? ($currentYear + 2));
    $notes       = trim($_POST['notes']        ?? '');

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM kankoor_waitlist WHERE id=?')->execute([$id]);
        $success = 'Entry deleted.';
        log_activity($pdo, 'kankoor_deleted', 'Waitlist entry #' . $id . ' removed');
    } elseif ($action === 'save') {
        if (!$name || !$fatherName || !$phone) {
            $error = 'Name, father\'s name, and phone are required.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE kankoor_waitlist
                         SET name=?,father_name=?,phone=?,father_phone=?,department=?,
                             year_register=?,year_graduation=?,notes=?
                         WHERE id=?'
                    )->execute([$name, $fatherName, $phone, $fatherPhone ?: null,
                                $dept ?: null, $yearReg, $yearGrad, $notes ?: null, $id]);
                    $success = 'Entry updated.';
                    log_activity($pdo, 'kankoor_updated', $name . ' (' . ($dept ?: 'no dept') . ')');
                } else {
                    $pdo->prepare(
                        'INSERT INTO kankoor_waitlist
                         (name,father_name,phone,father_phone,department,year_register,year_graduation,notes)
                         VALUES (?,?,?,?,?,?,?,?)'
                    )->execute([$name, $fatherName, $phone, $fatherPhone ?: null,
                                $dept ?: null, $yearReg, $yearGrad, $notes ?: null]);
                    $success = "Student \"{$name}\" added to waitlist.";
                    log_activity($pdo, 'kankoor_added', $name . ' — ' . ($dept ?: 'no dept') . ' ' . $yearReg . '→' . $yearGrad);
                }
            } catch (PDOException $e) {
                $error = 'Could not save entry. ' . $e->getMessage();
            }
        }
    }
}

// Search / filter
$q        = trim($_GET['q']    ?? '');
$deptF    = trim($_GET['dept'] ?? '');
$yearF    = trim($_GET['year'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(name LIKE ? OR father_name LIKE ? OR phone LIKE ?)';
    $like     = '%' . $q . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($deptF !== '') {
    $where[]  = 'department = ?';
    $params[] = $deptF;
}
if ($yearF !== '') {
    $where[]  = 'year_graduation = ?';
    $params[] = (int)$yearF;
}
$wClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total  = (int)$pdo->prepare("SELECT COUNT(*) FROM kankoor_waitlist $wClause")
                    ->execute($params) ? (function($p,$wc) use ($pdo) {
                        $c = $pdo->prepare("SELECT COUNT(*) FROM kankoor_waitlist $wc");
                        $c->execute($p);
                        return (int)$c->fetchColumn();
                    })($params, $wClause) : 0;

// Re-execute cleanly
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM kankoor_waitlist $wClause");
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT * FROM kankoor_waitlist $wClause
     ORDER BY year_graduation ASC, name ASC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Distinct graduation years for filter
$gradYears = $pdo->query(
    'SELECT DISTINCT year_graduation FROM kankoor_waitlist ORDER BY year_graduation'
)->fetchAll(PDO::FETCH_COLUMN);

function kwPageUrl(int $pg, string $q, string $dept, string $year): string {
    return '?' . http_build_query(array_filter(['q'=>$q,'dept'=>$dept,'year'=>$year,'page'=>$pg]));
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Kankoor Waitlist</h1>
            <p class="fluent-caption mt-1">
                <?= $total ?> student<?= $total !== 1 ? 's' : '' ?> registered
            </p>
        </div>
        <button type="button" class="fluent-btn-accent fluent-btn" id="openAddModal">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Student
        </button>
    </div>

    <?php if ($success): ?>
    <div class="fluent-alert fluent-alert-success mb-4" data-flash><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger mb-4" data-flash><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search + Filter -->
    <form method="get" class="fluent-card px-4 py-3 mb-4 fluent-fade-in" style="animation-delay:40ms;">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="fluent-input flex-1" style="min-width:200px;">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Search name, father name, phone…" style="width:100%;">
            </div>
            <?php if (!empty($allDepts)): ?>
            <div class="fluent-input" style="min-width:170px;">
                <select name="dept" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($allDepts as $d): ?>
                    <option value="<?= htmlspecialchars($d['name_en']) ?>"
                            <?= $deptF === $d['name_en'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name_en']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (!empty($gradYears)): ?>
            <div class="fluent-input" style="min-width:150px;">
                <select name="year" onchange="this.form.submit()">
                    <option value="">All Grad. Years</option>
                    <?php foreach ($gradYears as $y): ?>
                    <option value="<?= $y ?>" <?= $yearF == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="fluent-btn">Search</button>
            <?php if ($q || $deptF || $yearF): ?>
            <a href="?" class="fluent-btn">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Father's Name</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th style="text-align:center;">Reg. Year</th>
                    <th style="text-align:center;">Grad. Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($entries)): ?>
            <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:var(--text-tertiary);">
                    No entries found.
                </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($entries as $i => $e): ?>
            <tr>
                <td style="color:var(--text-tertiary);font-size:12px;"><?= $offset + $i + 1 ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($e['name']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($e['father_name']) ?></td>
                <td>
                    <span style="font-size:13px;"><?= htmlspecialchars($e['phone']) ?></span>
                    <?php if ($e['father_phone']): ?>
                    <div style="font-size:11px;color:var(--text-tertiary);">F: <?= htmlspecialchars($e['father_phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($e['department']): ?>
                    <span class="fluent-badge"><?= htmlspecialchars($e['department']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="text-align:center;font-weight:600;color:var(--text-secondary);">
                    <?= (int)$e['year_register'] ?>
                </td>
                <td style="text-align:center;">
                    <span class="fluent-badge fluent-badge-success"><?= (int)$e['year_graduation'] ?></span>
                </td>
                <td>
                    <div class="flex gap-2">
                        <button type="button" class="fluent-btn open-edit-modal"
                                style="padding:3px 10px;font-size:12px;"
                                data-id="<?= $e['id'] ?>"
                                data-name="<?= htmlspecialchars($e['name'], ENT_QUOTES) ?>"
                                data-father="<?= htmlspecialchars($e['father_name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($e['phone'], ENT_QUOTES) ?>"
                                data-fphone="<?= htmlspecialchars($e['father_phone'] ?? '', ENT_QUOTES) ?>"
                                data-dept="<?= htmlspecialchars($e['department'] ?? '', ENT_QUOTES) ?>"
                                data-reg="<?= (int)$e['year_register'] ?>"
                                data-grad="<?= (int)$e['year_graduation'] ?>"
                                data-notes="<?= htmlspecialchars($e['notes'] ?? '', ENT_QUOTES) ?>">
                            Edit
                        </button>
                        <form method="POST"
                              onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($e['name'])) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
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

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center gap-2 mt-4">
        <?php if ($page > 1): ?>
        <a href="<?= kwPageUrl($page-1,$q,$deptF,$yearF) ?>" class="fluent-btn" style="padding:4px 12px;font-size:13px;">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
        <a href="<?= kwPageUrl($p,$q,$deptF,$yearF) ?>" class="fluent-btn"
           style="padding:4px 12px;font-size:13px;min-width:36px;text-align:center;<?= $p===$page?'background:var(--accent);color:#fff;border-color:var(--accent);':''?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
        <a href="<?= kwPageUrl($page+1,$q,$deptF,$yearF) ?>" class="fluent-btn" style="padding:4px 12px;font-size:13px;">Next →</a>
        <?php endif; ?>
        <span class="fluent-caption" style="margin-left:8px;">
            <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?>
        </span>
    </div>
    <?php endif; ?>
</main>

<!-- ============================================================
     ADD / EDIT MODAL
============================================================ -->
<div id="kwModal"
     class="fixed inset-0 z-50 flex items-center justify-center hidden"
     style="background:rgba(0,0,0,0.35);backdrop-filter:blur(4px);">
    <div class="fluent-card w-full mx-4 fluent-fade-in" style="max-width:520px;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto;">

        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid var(--border);">
            <h2 class="fluent-h3" id="modalTitle">Add Student</h2>
            <button id="closeModal" type="button"
                    class="w-8 h-8 rounded-md flex items-center justify-center"
                    style="color:var(--text-tertiary);"
                    onmouseover="this.style.background='var(--hover)'"
                    onmouseout="this.style.background='transparent'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" class="px-6 py-5 space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="mId" value="0">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Full Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="name" id="mName" required placeholder="Ahmad Karimi">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Father's Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="father_name" id="mFather" required placeholder="Mohammad Karimi">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Active Phone *</label>
                    <div class="fluent-input">
                        <input type="tel" name="phone" id="mPhone" required placeholder="07XXXXXXXX">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Father's Phone
                        <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;color:var(--text-tertiary);">— optional</span>
                    </label>
                    <div class="fluent-input">
                        <input type="tel" name="father_phone" id="mFPhone" placeholder="07XXXXXXXX">
                    </div>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Department</label>
                <div class="fluent-input">
                    <select name="department" id="mDept" onchange="calcGradYear()">
                        <option value="">— Select —</option>
                        <?php foreach ($allDepts as $d): ?>
                        <option value="<?= htmlspecialchars($d['name_en']) ?>">
                            <?= htmlspecialchars($d['name_en']) ?>
                            <?php if ($d['name_ps']): ?>(<?= htmlspecialchars($d['name_ps']) ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Register Year</label>
                    <div class="fluent-input">
                        <input type="number" name="year_register" id="mRegYear"
                               min="2000" max="2100" value="<?= $currentYear ?>"
                               onchange="calcGradYear()">
                    </div>
                    <p class="fluent-caption mt-1">Auto-set to current year</p>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Graduation Year</label>
                    <div class="fluent-input">
                        <input type="number" name="year_graduation" id="mGradYear"
                               min="2000" max="2100" value="<?= $currentYear + 2 ?>">
                    </div>
                    <p class="fluent-caption mt-1">Auto-calculated; editable</p>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Notes
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;color:var(--text-tertiary);">— optional</span>
                </label>
                <div class="fluent-input" style="padding:0;">
                    <textarea name="notes" id="mNotes" rows="2"
                              style="width:100%;padding:8px 12px;background:transparent;border:none;outline:none;font-size:13px;resize:vertical;"
                              placeholder="Any additional notes…"></textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="fluent-btn-accent fluent-btn" id="mSubmitBtn">Add Student</button>
                <button type="button" class="fluent-btn" id="closeModal2">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const deptYears = <?= json_encode($deptYears) ?>;
const currentYear = <?= $currentYear ?>;

function calcGradYear() {
    const dept    = document.getElementById('mDept').value;
    const regYear = parseInt(document.getElementById('mRegYear').value) || currentYear;
    const yrs     = deptYears[dept] ?? 2;
    document.getElementById('mGradYear').value = regYear + yrs;
}

function openModal(data) {
    document.getElementById('mId').value          = data.id    || '0';
    document.getElementById('mName').value        = data.name  || '';
    document.getElementById('mFather').value      = data.father|| '';
    document.getElementById('mPhone').value       = data.phone || '';
    document.getElementById('mFPhone').value      = data.fphone|| '';
    document.getElementById('mNotes').value       = data.notes || '';
    document.getElementById('mRegYear').value     = data.reg   || currentYear;
    document.getElementById('mGradYear').value    = data.grad  || (currentYear + 2);

    const deptSel = document.getElementById('mDept');
    deptSel.value = data.dept || '';

    const isEdit = !!data.id;
    document.getElementById('modalTitle').textContent = isEdit ? 'Edit Entry' : 'Add Student';
    document.getElementById('mSubmitBtn').textContent = isEdit ? 'Update' : 'Add Student';

    document.getElementById('kwModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('kwModal').classList.add('hidden');
}

document.getElementById('openAddModal').addEventListener('click', function() {
    openModal({});
});

document.querySelectorAll('.open-edit-modal').forEach(function(btn) {
    btn.addEventListener('click', function() {
        openModal({
            id:     this.dataset.id,
            name:   this.dataset.name,
            father: this.dataset.father,
            phone:  this.dataset.phone,
            fphone: this.dataset.fphone,
            dept:   this.dataset.dept,
            reg:    this.dataset.reg,
            grad:   this.dataset.grad,
            notes:  this.dataset.notes,
        });
    });
});

document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('closeModal2').addEventListener('click', closeModal);
document.getElementById('kwModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

<?php if ($error): ?>
// Re-open modal with submitted data if there was a validation error
openModal({
    id:     '<?= (int)($_POST['id'] ?? 0) ?>',
    name:   <?= json_encode($_POST['name'] ?? '') ?>,
    father: <?= json_encode($_POST['father_name'] ?? '') ?>,
    phone:  <?= json_encode($_POST['phone'] ?? '') ?>,
    fphone: <?= json_encode($_POST['father_phone'] ?? '') ?>,
    dept:   <?= json_encode($_POST['department'] ?? '') ?>,
    reg:    <?= (int)($_POST['year_register'] ?? $currentYear) ?>,
    grad:   <?= (int)($_POST['year_graduation'] ?? ($currentYear + 2)) ?>,
    notes:  <?= json_encode($_POST['notes'] ?? '') ?>,
});
<?php endif; ?>
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
