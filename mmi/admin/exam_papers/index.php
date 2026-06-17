<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/departments.php';
require_role('admin');

$pageTitle = 'Exam Papers — ' . SITE_NAME;

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $id      = (int)($_POST['id'] ?? 0);
    $adminId = (int)$_SESSION['user_id'];

    if ($action === 'save_header' && $id) {
        $pdo->prepare(
            'UPDATE exam_papers SET exam_date=?, year_label=?, term_label=? WHERE id=?'
        )->execute([
            trim($_POST['exam_date'] ?? '') ?: null,
            trim($_POST['year_label'] ?? '') ?: null,
            trim($_POST['term_label'] ?? '') ?: null,
            $id,
        ]);
        $_SESSION['_ap_flash'] = ['ok', 'Header data saved.'];
    }
    elseif ($action === 'approve' && $id) {
        $pdo->prepare('UPDATE exam_papers SET status="approved", reviewed_at=NOW(), reviewed_by=?, admin_note=NULL WHERE id=?')
            ->execute([$adminId, $id]);
        $_SESSION['_ap_flash'] = ['ok', 'Exam paper approved.'];
    }
    elseif ($action === 'reject' && $id) {
        $note = trim($_POST['admin_note'] ?? '') ?: 'Please revise and resubmit.';
        $pdo->prepare('UPDATE exam_papers SET status="rejected", reviewed_at=NOW(), reviewed_by=?, admin_note=? WHERE id=?')
            ->execute([$adminId, $note, $id]);
        $_SESSION['_ap_flash'] = ['ok', 'Exam paper rejected.'];
    }
    elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM exam_papers WHERE id=?')->execute([$id]);
        $_SESSION['_ap_flash'] = ['ok', 'Exam paper deleted.'];
    }

    header('Location: ' . ($_POST['redirect'] ?? 'index.php'));
    exit;
}
$flash = $_SESSION['_ap_flash'] ?? null;
unset($_SESSION['_ap_flash']);

// ── Teacher filter ────────────────────────────────────────────────
$teachers = $pdo->query(
    'SELECT t.id, u.name, t.teacher_no
     FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE EXISTS (SELECT 1 FROM exam_papers ep WHERE ep.teacher_id=t.id)
     ORDER BY u.name'
)->fetchAll();

$filterTeacher = (int)($_GET['teacher'] ?? 0);
$filterStatus  = $_GET['status'] ?? '';

$where = ['1=1']; $params = [];
if ($filterTeacher) { $where[]='ep.teacher_id=?'; $params[]=$filterTeacher; }
if (in_array($filterStatus, ['draft','submitted','approved','rejected'])) { $where[]='ep.status=?'; $params[]=$filterStatus; }

$stmt = $pdo->prepare(
    'SELECT ep.*, u.name AS teacher_name, t.teacher_no,
            (SELECT COUNT(*) FROM students s
             WHERE s.department = ep.department COLLATE utf8mb4_general_ci
               AND s.semester   = ep.semester   COLLATE utf8mb4_general_ci
               AND s.shift      = ep.shift      COLLATE utf8mb4_general_ci) AS student_count
     FROM exam_papers ep
     JOIN teachers t ON t.id=ep.teacher_id
     JOIN users u    ON u.id=t.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY FIELD(ep.status,"submitted","approved","rejected","draft"), ep.updated_at DESC'
);
$stmt->execute($params);
$papers = $stmt->fetchAll();

$pendingCount = (int)$pdo->query('SELECT COUNT(*) FROM exam_papers WHERE status="submitted"')->fetchColumn();

function apStatus(string $s): string {
    $m=['draft'=>['Draft','background:var(--surface-hover);color:var(--text-secondary);'],
        'submitted'=>['Pending','background:rgba(15,108,189,.12);color:#0f6cbd;'],
        'approved'=>['Approved','background:rgba(14,122,14,.12);color:#0e7a0e;'],
        'rejected'=>['Rejected','background:rgba(196,43,28,.12);color:#c42b1c;']];
    [$l,$st]=$m[$s]??['—','']; return '<span class="fluent-badge" style="font-size:10px;'.$st.'">'.$l.'</span>';
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Exam Papers</h1>
        <p class="fluent-caption mt-1">Review teacher-submitted exam papers, assign header data, and download.</p>
    </div>

    <?php if ($flash): ?>
    <div class="fluent-alert <?= $flash[0]==='ok'?'fluent-alert-success':'fluent-alert-danger' ?> mb-4" data-flash><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="fluent-card px-5 py-4 mb-5 fluent-fade-in" style="animation-delay:30ms;">
        <div class="flex flex-wrap gap-3 items-end">
            <div style="min-width:240px;">
                <label class="fluent-label block mb-1">Teacher</label>
                <div class="fluent-input">
                    <select name="teacher" onchange="this.form.submit()">
                        <option value="0">All Teachers</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $filterTeacher==$t['id']?'selected':'' ?>>
                            <?= htmlspecialchars($t['name']) ?><?= $t['teacher_no']?' ('.htmlspecialchars($t['teacher_no']).')':'' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="min-width:160px;">
                <label class="fluent-label block mb-1">Status</label>
                <div class="fluent-input">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All</option>
                        <?php foreach (['submitted'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','draft'=>'Draft'] as $k=>$l): ?>
                        <option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($pendingCount): ?>
            <span class="fluent-badge" style="background:rgba(15,108,189,.12);color:#0f6cbd;"><?= $pendingCount ?> pending</span>
            <?php endif; ?>
            <?php if ($filterTeacher || $filterStatus): ?><a href="index.php" class="fluent-btn" style="font-size:13px;">Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($papers)): ?>
    <div class="fluent-card p-12 text-center fluent-fade-in">
        <p style="color:var(--text-tertiary);">No exam papers found.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3 fluent-fade-in" style="animation-delay:50ms;">
    <?php foreach ($papers as $p): $qd = json_decode($p['questions'] ?? '', true) ?: []; ?>
        <?php $nMcq = count($qd['mcqs'] ?? []); $nDesc = count($qd['descriptive'] ?? []); ?>
        <div class="fluent-card overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3" style="border-bottom:1px solid var(--border);">
                <div class="flex-1">
                    <?php $isUp = ($p['source'] ?? 'form') === 'upload'; ?>
                    <p style="font-weight:700;font-size:15px;"><?= htmlspecialchars($p['subject_name']) ?>
                        <span class="fluent-badge <?= $p['exam_type']==='final'?'fluent-badge-success':'' ?>" style="text-transform:capitalize;margin-left:6px;"><?= $p['exam_type'] ?></span>
                        <span class="fluent-badge" style="margin-left:2px;"><?= $p['language']==='english'?'English':'پښتو' ?></span>
                        <?php if ($isUp): ?><span class="fluent-badge" style="margin-left:2px;background:rgba(122,61,179,.12);color:#7a3db3;">Uploaded file</span><?php endif; ?>
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);">
                        <?= htmlspecialchars($p['teacher_name']) ?> ·
                        <?= dept_label($pdo, $p['department']) ?> · <?= htmlspecialchars($p['semester'] ?? '') ?> · <?= htmlspecialchars($p['shift'] ?? '') ?>
                    </p>
                </div>
                <span style="font-size:12px;color:var(--text-tertiary);">
                    <?php if ($isUp): ?>
                        <?= !empty($p['file_path']) ? '.docx uploaded' : 'no file' ?>
                    <?php else: ?>
                        <?= $nMcq ?> MCQ · <?= $nDesc ?> descriptive
                    <?php endif; ?>
                </span>
                <?= apStatus($p['status']) ?>
            </div>

            <div class="px-5 py-4">
                <?php if ($p['status'] === 'draft'): ?>
                <div class="flex items-center justify-between">
                    <p style="font-size:13px;color:var(--text-tertiary);">Still a draft — waiting for the teacher to submit.</p>
                    <form method="POST" onsubmit="return confirm('Delete this draft exam paper permanently?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button class="fluent-btn" style="font-size:13px;color:#c42b1c;border-color:rgba(196,43,28,.4);">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <!-- Header data form -->
                <form method="POST" class="flex flex-wrap gap-3 items-end mb-3">
                    <input type="hidden" name="action" value="save_header">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <div style="min-width:130px;">
                        <label class="fluent-label block mb-1">تاریخ (Date)</label>
                        <div class="fluent-input"><input type="text" name="exam_date" value="<?= htmlspecialchars($p['exam_date'] ?? '') ?>" placeholder="1405/03/15"></div>
                    </div>
                    <div style="min-width:120px;">
                        <label class="fluent-label block mb-1">وخت (Time)</label>
                        <div class="fluent-input" style="background:var(--surface-hover);">
                            <input type="text" value="<?= htmlspecialchars($p['shift'] ?? '—') ?>" readonly title="From class shift">
                        </div>
                    </div>
                    <div style="min-width:120px;">
                        <label class="fluent-label block mb-1">کال (Year)</label>
                        <div class="fluent-input"><input type="text" name="year_label" value="<?= htmlspecialchars($p['year_label'] ?? '۱۴۰۵ هـ ش') ?>"></div>
                    </div>
                    <div style="min-width:140px;">
                        <label class="fluent-label block mb-1">سمستر (Term)</label>
                        <div class="fluent-input"><input type="text" name="term_label" value="<?= htmlspecialchars($p['term_label'] ?? 'بهاري سمستر') ?>"></div>
                    </div>
                    <button class="fluent-btn" style="font-size:13px;">Save Header</button>
                </form>

                <div class="flex flex-wrap gap-2 items-center">
                    <a href="download.php?id=<?= (int)$p['id'] ?>" class="fluent-btn" style="font-size:13px;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download (blank)
                    </a>
                    <?php $sc = (int)$p['student_count']; ?>
                    <a href="download.php?id=<?= (int)$p['id'] ?>&mailing=1"
                       class="fluent-btn-accent fluent-btn" style="font-size:13px;<?= $sc===0?'opacity:.4;pointer-events:none;':'' ?>"
                       title="One paper per student with name &amp; father filled in">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Mailing Download (<?= $sc ?> student<?= $sc!==1?'s':'' ?>)
                    </a>
                    <a href="download.php?id=<?= (int)$p['id'] ?>&preview=1" target="_blank" class="fluent-btn" style="font-size:13px;">Preview</a>

                    <?php if ($p['status'] === 'submitted'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button class="fluent-btn" style="font-size:13px;color:#0e7a0e;border-color:rgba(14,122,14,.4);">✓ Approve</button>
                    </form>
                    <button type="button" class="fluent-btn" style="font-size:13px;color:#c42b1c;border-color:rgba(196,43,28,.4);"
                            onclick="document.getElementById('rej<?= (int)$p['id'] ?>').style.display='flex'">Reject</button>
                    <?php endif; ?>

                    <form method="POST" style="margin-left:auto;" onsubmit="return confirm('Delete this exam paper permanently? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button class="fluent-btn" style="font-size:13px;color:#c42b1c;border-color:rgba(196,43,28,.4);">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </form>
                </div>

                <!-- Reject note -->
                <div id="rej<?= (int)$p['id'] ?>" style="display:none;margin-top:10px;">
                    <form method="POST" style="display:flex;gap:8px;align-items:center;">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <div class="fluent-input" style="flex:1;"><input type="text" name="admin_note" placeholder="Reason for rejection…" required></div>
                        <button class="fluent-btn" style="font-size:13px;color:#c42b1c;border-color:rgba(196,43,28,.4);">Confirm</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
