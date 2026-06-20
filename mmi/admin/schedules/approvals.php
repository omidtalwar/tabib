<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/departments.php';
require_role('admin');

$pageTitle = 'Schedule Approvals — ' . SITE_NAME;
$days = [1=>'Saturday', 2=>'Sunday', 3=>'Monday', 4=>'Tuesday', 5=>'Wednesday', 6=>'Thursday'];

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $adminId   = (int)$_SESSION['user_id'];

    if ($action === 'approve_all' && $teacherId) {
        $pdo->prepare(
            'UPDATE teacher_schedules SET status="approved", reviewed_at=NOW(), reviewed_by=?, admin_note=NULL
             WHERE teacher_id=? AND status="submitted"'
        )->execute([$adminId, $teacherId]);
        try { require_once __DIR__.'/../../includes/activity.php';
              log_activity($pdo, 'schedule_approved', "Approved schedule for teacher #$teacherId"); } catch (Exception $e) {}
        $_SESSION['_appr_flash'] = ['ok', 'Schedule approved.'];
    }

    elseif ($action === 'reject_all' && $teacherId) {
        $note = trim($_POST['admin_note'] ?? '') ?: 'Please review and resubmit.';
        $pdo->prepare(
            'UPDATE teacher_schedules SET status="rejected", reviewed_at=NOW(), reviewed_by=?, admin_note=?
             WHERE teacher_id=? AND status="submitted"'
        )->execute([$adminId, $note, $teacherId]);
        $_SESSION['_appr_flash'] = ['ok', 'Schedule rejected — teacher notified.'];
    }

    elseif ($action === 'approve_slot') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare(
            'UPDATE teacher_schedules SET status="approved", reviewed_at=NOW(), reviewed_by=?, admin_note=NULL
             WHERE id=? AND status="submitted"'
        )->execute([$adminId, $id]);
        $_SESSION['_appr_flash'] = ['ok', 'Slot approved.'];
    }

    elseif ($action === 'reject_slot') {
        $id   = (int)($_POST['id'] ?? 0);
        $note = trim($_POST['admin_note'] ?? '') ?: 'Rejected.';
        $pdo->prepare(
            'UPDATE teacher_schedules SET status="rejected", reviewed_at=NOW(), reviewed_by=?, admin_note=?
             WHERE id=? AND status="submitted"'
        )->execute([$adminId, $note, $id]);
        $_SESSION['_appr_flash'] = ['ok', 'Slot rejected.'];
    }

    elseif ($action === 'edit_slot') {
        $id    = (int)($_POST['id'] ?? 0);
        $subj  = trim($_POST['subject_name'] ?? '');
        $day   = (int)($_POST['day_of_week'] ?? 0);
        $ts    = trim($_POST['time_start'] ?? '') ?: null;
        $te    = trim($_POST['time_end']   ?? '') ?: null;
        $room  = trim($_POST['room']       ?? '') ?: null;
        if ($subj && $day) {
            $pdo->prepare(
                'UPDATE teacher_schedules SET subject_name=?, day_of_week=?, time_start=?, time_end=?, room=?
                 WHERE id=?'
            )->execute([$subj, $day, $ts, $te, $room, $id]);
            $_SESSION['_appr_flash'] = ['ok', 'Slot updated.'];
        }
    }

    elseif ($action === 'delete_slot') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM teacher_schedules WHERE id=?')->execute([$id]);
        $_SESSION['_appr_flash'] = ['ok', 'Slot deleted.'];
    }

    $redirect = $_POST['redirect'] ?? 'approvals.php';
    header('Location: ' . $redirect);
    exit;
}

$flash = $_SESSION['_appr_flash'] ?? null;
unset($_SESSION['_appr_flash']);

$viewTeacher = (int)($_GET['teacher'] ?? 0);

// ── Detail view: one teacher's submitted/reviewed slots ──────────
$detail = null;
if ($viewTeacher) {
    $tStmt = $pdo->prepare(
        'SELECT t.id, u.name, t.teacher_no FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=?'
    );
    $tStmt->execute([$viewTeacher]);
    $detail = $tStmt->fetch();

    if ($detail) {
        $sStmt = $pdo->prepare(
            'SELECT * FROM teacher_schedules
             WHERE teacher_id=? AND status IN ("submitted","approved","rejected")
             ORDER BY day_of_week, time_start, id'
        );
        $sStmt->execute([$viewTeacher]);
        $detailSlots = $sStmt->fetchAll();

        $byDay = [];
        $pendingCount = 0;
        foreach ($detailSlots as $s) {
            $byDay[$s['day_of_week']][] = $s;
            if ($s['status'] === 'submitted') $pendingCount++;
        }
    }
}

// ── List view: teachers with submitted slots ─────────────────────
$pending = [];
if (!$viewTeacher) {
    $pending = $pdo->query(
        'SELECT t.id AS teacher_id, u.name, t.teacher_no,
                COUNT(*) AS slot_count,
                MIN(ts.submitted_at) AS submitted_at
         FROM teacher_schedules ts
         JOIN teachers t ON t.id = ts.teacher_id
         JOIN users u    ON u.id = t.user_id
         WHERE ts.status = "submitted"
         GROUP BY t.id, u.name, t.teacher_no
         ORDER BY submitted_at ASC'
    )->fetchAll();
}

function apprChip(string $status): string {
    $map = [
        'submitted' => ['Pending',  'background:rgba(15,108,189,.12);color:#0f6cbd;'],
        'approved'  => ['Approved', 'background:rgba(14,122,14,.12);color:#0e7a0e;'],
        'rejected'  => ['Rejected', 'background:rgba(196,43,28,.12);color:#c42b1c;'],
    ];
    [$lbl, $st] = $map[$status] ?? ['—',''];
    return '<span class="fluent-badge" style="font-size:10px;'.$st.'">'.$lbl.'</span>';
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<style>
.slot-card  { border-radius:8px;padding:9px 11px;margin-bottom:8px;border:1px solid var(--border);background:var(--surface); }
.day-col-h  { font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-tertiary);padding:4px 4px 8px;border-bottom:1px solid var(--border);margin-bottom:8px; }
.slot-actions { display:flex;gap:4px;margin-top:6px; }
.mini-btn   { font-size:10px;padding:2px 7px;border-radius:5px;border:1px solid var(--border);background:var(--surface);cursor:pointer;text-decoration:none;color:var(--text-secondary); }
</style>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

<?php if ($flash): ?>
<div class="fluent-alert <?= $flash[0]==='ok' ? 'fluent-alert-success' : 'fluent-alert-danger' ?> mb-4" data-flash>
    <?= htmlspecialchars($flash[1]) ?>
</div>
<?php endif; ?>

<?php if (!$viewTeacher): ?>
<!-- ══════════════ LIST VIEW ══════════════ -->
<div class="flex items-center justify-between mb-5 fluent-fade-in">
    <div>
        <h1 class="fluent-h1">Schedule Approvals</h1>
        <p class="fluent-caption mt-1">Review weekly schedules submitted by teachers.</p>
    </div>
    <a href="index.php" class="fluent-btn" style="font-size:13px;">Class Timetables →</a>
</div>

<?php if (empty($pending)): ?>
<div class="fluent-card p-12 text-center fluent-fade-in" style="animation-delay:40ms;">
    <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
    </svg>
    <p style="color:var(--text-tertiary);">No pending schedule submissions. All caught up.</p>
</div>
<?php else: ?>
<div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:40ms;">
    <table class="fluent-table">
        <thead>
            <tr>
                <th>Teacher</th>
                <th>ID</th>
                <th style="text-align:center;">Slots</th>
                <th>Submitted</th>
                <th style="width:140px;">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $p): ?>
        <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
            <td style="font-family:monospace;font-size:12px;color:var(--accent);"><?= htmlspecialchars($p['teacher_no'] ?? '—') ?></td>
            <td style="text-align:center;"><span class="fluent-badge"><?= (int)$p['slot_count'] ?></span></td>
            <td style="font-size:12px;color:var(--text-tertiary);">
                <?= $p['submitted_at'] ? date('d M Y, H:i', strtotime($p['submitted_at'])) : '—' ?>
            </td>
            <td>
                <a href="?teacher=<?= (int)$p['teacher_id'] ?>" class="fluent-btn-accent fluent-btn" style="padding:4px 12px;font-size:12px;">
                    Review
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php elseif (!$detail): ?>
<div class="fluent-card p-10 text-center"><p style="color:var(--text-tertiary);">Teacher not found. <a href="approvals.php" style="color:var(--accent);">Back</a></p></div>

<?php else: ?>
<!-- ══════════════ DETAIL VIEW ══════════════ -->
<div class="flex items-center gap-3 mb-5 fluent-fade-in">
    <a href="approvals.php" class="fluent-btn" style="padding:4px 10px;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div>
        <h1 class="fluent-h1"><?= htmlspecialchars($detail['name']) ?></h1>
        <p class="fluent-caption mt-0.5">
            <?= htmlspecialchars($detail['teacher_no'] ?? '') ?> ·
            <?= $pendingCount ?> pending slot<?= $pendingCount!==1?'s':'' ?>
        </p>
    </div>

    <?php if ($pendingCount > 0): ?>
    <div class="flex gap-2 ml-auto">
        <form method="POST" onsubmit="return confirm('Approve all pending slots for this teacher?');">
            <input type="hidden" name="action" value="approve_all">
            <input type="hidden" name="teacher_id" value="<?= (int)$detail['id'] ?>">
            <input type="hidden" name="redirect" value="approvals.php?teacher=<?= (int)$detail['id'] ?>">
            <button class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Approve All
            </button>
        </form>
        <button type="button" class="fluent-btn" style="font-size:13px;color:#c42b1c;border-color:rgba(196,43,28,.4);"
                onclick="document.getElementById('rejectAllBox').style.display='flex'">
            Reject All
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Reject-all note box -->
<div id="rejectAllBox" class="fluent-card p-4 mb-4" style="display:none;align-items:center;gap:10px;border-color:rgba(196,43,28,.3);">
    <form method="POST" style="display:flex;gap:10px;align-items:center;width:100%;">
        <input type="hidden" name="action" value="reject_all">
        <input type="hidden" name="teacher_id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php?teacher=<?= (int)$detail['id'] ?>">
        <div class="fluent-input flex-1">
            <input type="text" name="admin_note" placeholder="Reason for rejection (teacher will see this)…" required>
        </div>
        <button class="fluent-btn" style="font-size:13px;color:#c42b1c;border-color:rgba(196,43,28,.4);">Confirm Reject</button>
        <button type="button" class="fluent-btn" style="font-size:13px;" onclick="document.getElementById('rejectAllBox').style.display='none'">Cancel</button>
    </form>
</div>

<?php if (empty($detailSlots)): ?>
<div class="fluent-card p-10 text-center"><p style="color:var(--text-tertiary);">No slots to review.</p></div>
<?php else: ?>
<div class="fluent-card p-4 fluent-fade-in" style="overflow-x:auto;animation-delay:40ms;">
    <div style="display:grid;grid-template-columns:repeat(6,minmax(160px,1fr));gap:12px;min-width:980px;">
        <?php foreach ($days as $dayNum => $dayName): ?>
        <div>
            <div class="day-col-h"><?= $dayName ?></div>
            <?php if (!empty($byDay[$dayNum])): ?>
                <?php foreach ($byDay[$dayNum] as $sl): ?>
                <div class="slot-card" style="<?= $sl['status']==='approved' ? 'border-color:rgba(14,122,14,.4);' : ($sl['status']==='rejected' ? 'border-color:rgba(196,43,28,.4);opacity:.7;' : '') ?>">
                    <?php if ($sl['time_start']): ?>
                    <p style="font-size:10px;font-weight:600;color:var(--text-tertiary);margin-bottom:3px;">
                        <?= substr($sl['time_start'],0,5) ?><?= $sl['time_end'] ? ' – '.substr($sl['time_end'],0,5) : '' ?>
                    </p>
                    <?php endif; ?>
                    <p style="font-size:13px;font-weight:700;margin-bottom:3px;"><?= htmlspecialchars($sl['subject_name']) ?></p>
                    <p style="font-size:11px;color:var(--accent);margin-bottom:2px;">
                        <?= dept_label($pdo, $sl['department']) ?> · <?= htmlspecialchars($sl['semester']) ?>
                    </p>
                    <p style="font-size:10px;color:var(--text-tertiary);margin-bottom:3px;"><?= htmlspecialchars($sl['shift']) ?></p>
                    <?php if ($sl['room']): ?>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-bottom:4px;">Room: <?= htmlspecialchars($sl['room']) ?></p>
                    <?php endif; ?>
                    <?= apprChip($sl['status']) ?>

                    <?php if ($sl['status'] === 'submitted'): ?>
                    <div class="slot-actions">
                        <form method="POST">
                            <input type="hidden" name="action" value="approve_slot">
                            <input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
                            <input type="hidden" name="redirect" value="approvals.php?teacher=<?= (int)$detail['id'] ?>">
                            <button class="mini-btn" style="color:#0e7a0e;border-color:rgba(14,122,14,.4);" title="Approve">✓</button>
                        </form>
                        <button type="button" class="mini-btn"
                                onclick="openEdit(<?= htmlspecialchars(json_encode($sl), ENT_QUOTES) ?>)" title="Edit">✎</button>
                        <form method="POST" onsubmit="return confirm('Delete this slot?');">
                            <input type="hidden" name="action" value="delete_slot">
                            <input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
                            <input type="hidden" name="redirect" value="approvals.php?teacher=<?= (int)$detail['id'] ?>">
                            <button class="mini-btn" style="color:#c42b1c;border-color:rgba(196,43,28,.4);" title="Delete">&times;</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <p style="font-size:12px;color:var(--text-tertiary);padding:8px 4px;">—</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Edit slot modal -->
<div id="editModal" class="fixed inset-0 z-50 items-center justify-center" style="display:none;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);">
    <div class="fluent-card w-full max-w-md mx-4" style="box-shadow:var(--shadow-lg);">
        <div class="px-6 py-4" style="border-bottom:1px solid var(--border);">
            <h2 class="fluent-h3">Edit Slot</h2>
        </div>
        <form method="POST" class="px-6 py-5 space-y-3">
            <input type="hidden" name="action" value="edit_slot">
            <input type="hidden" name="id" id="eId">
            <input type="hidden" name="redirect" value="approvals.php?teacher=<?= (int)$detail['id'] ?>">
            <div>
                <label class="fluent-label block mb-1.5">Subject</label>
                <div class="fluent-input"><input type="text" name="subject_name" id="eSubj" required></div>
            </div>
            <div>
                <label class="fluent-label block mb-1.5">Day</label>
                <div class="fluent-input">
                    <select name="day_of_week" id="eDay" required>
                        <?php foreach ($days as $num=>$name): ?>
                        <option value="<?= $num ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="fluent-label block mb-1.5">Start</label>
                    <div class="fluent-input"><input type="time" name="time_start" id="eStart"></div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">End</label>
                    <div class="fluent-input"><input type="time" name="time_end" id="eEnd"></div>
                </div>
            </div>
            <div>
                <label class="fluent-label block mb-1.5">Room</label>
                <div class="fluent-input"><input type="text" name="room" id="eRoom"></div>
            </div>
            <div class="flex gap-2 pt-2">
                <button class="fluent-btn-accent fluent-btn" style="font-size:13px;">Save</button>
                <button type="button" class="fluent-btn" style="font-size:13px;" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(sl) {
    document.getElementById('eId').value    = sl.id;
    document.getElementById('eSubj').value  = sl.subject_name || '';
    document.getElementById('eDay').value   = sl.day_of_week;
    document.getElementById('eStart').value = sl.time_start ? sl.time_start.substring(0,5) : '';
    document.getElementById('eEnd').value   = sl.time_end ? sl.time_end.substring(0,5) : '';
    document.getElementById('eRoom').value  = sl.room || '';
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php endif; ?>

</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
