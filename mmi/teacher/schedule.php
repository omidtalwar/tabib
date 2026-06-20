<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/departments.php';
require_role('teacher');

$pageTitle = 'My Schedule — ' . SITE_NAME;
$user = current_user();

// ── This teacher's record ─────────────────────────────────────────
$tStmt = $pdo->prepare('SELECT t.id, u.name FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.user_id=?');
$tStmt->execute([$user['id']]);
$tRow = $tStmt->fetch();
$teacherId   = $tRow['id']   ?? null;
$teacherName = $tRow['name'] ?? '';

if (!$teacherId) { die('Teacher profile not found.'); }

$days = [1=>'Saturday', 2=>'Sunday', 3=>'Monday', 4=>'Tuesday', 5=>'Wednesday', 6=>'Thursday'];

// ── POST handlers ─────────────────────────────────────────────────
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_slot') {
        $dept  = trim($_POST['department'] ?? '');
        $sem   = trim($_POST['semester']   ?? '');
        $shift = trim($_POST['shift']      ?? '');
        $day   = (int)($_POST['day_of_week'] ?? 0);
        $subj  = trim($_POST['subject_name'] ?? '');
        $ts    = trim($_POST['time_start'] ?? '') ?: null;
        $te    = trim($_POST['time_end']   ?? '') ?: null;
        $room  = trim($_POST['room']       ?? '') ?: null;

        if (!$dept || !$sem || !$shift || !$day || !$subj) {
            $flash = ['err', 'Department, semester, shift, day and subject are all required.'];
        } else {
            $pdo->prepare(
                'INSERT INTO teacher_schedules
                 (teacher_id, department, semester, shift, day_of_week, subject_name, time_start, time_end, room, status)
                 VALUES (?,?,?,?,?,?,?,?,?,"draft")'
            )->execute([$teacherId, $dept, $sem, $shift, $day, $subj, $ts, $te, $room]);
            $flash = ['ok', 'Slot added to your draft schedule.'];
        }
    }

    elseif ($action === 'delete_slot') {
        $id = (int)($_POST['id'] ?? 0);
        // Only allow deleting own slots that aren't approved
        $pdo->prepare(
            'DELETE FROM teacher_schedules WHERE id=? AND teacher_id=? AND status IN ("draft","rejected")'
        )->execute([$id, $teacherId]);
        $flash = ['ok', 'Slot removed.'];
    }

    elseif ($action === 'submit') {
        // Push all draft + rejected slots to submitted
        $upd = $pdo->prepare(
            'UPDATE teacher_schedules
             SET status="submitted", submitted_at=NOW(), admin_note=NULL
             WHERE teacher_id=? AND status IN ("draft","rejected")'
        );
        $upd->execute([$teacherId]);
        $n = $upd->rowCount();
        if ($n > 0) {
            try {
                require_once __DIR__ . '/../includes/activity.php';
                log_activity($pdo, 'schedule_submitted', "Teacher submitted $n schedule slot(s) for approval");
            } catch (Exception $e) {}
            $flash = ['ok', "Submitted $n slot(s) to the administration for approval."];
        } else {
            $flash = ['err', 'Nothing to submit — add slots first.'];
        }
    }

    elseif ($action === 'recall') {
        // Pull submitted (not yet reviewed) back to draft for editing
        $pdo->prepare(
            'UPDATE teacher_schedules SET status="draft", submitted_at=NULL
             WHERE teacher_id=? AND status="submitted"'
        )->execute([$teacherId]);
        $flash = ['ok', 'Submission recalled — you can edit again.'];
    }

    // PRG redirect
    $_SESSION['_sched_flash'] = $flash;
    header('Location: schedule.php');
    exit;
}

if (isset($_SESSION['_sched_flash'])) {
    $flash = $_SESSION['_sched_flash'];
    unset($_SESSION['_sched_flash']);
}

// ── Load this teacher's classes + subjects from teacher_courses ───
$classes = []; // "dept||sem||shift" => ['meta'=>..., 'subjects'=>[names]]
$cStmt = $pdo->prepare(
    'SELECT DISTINCT department, semester, shift, subject_name
     FROM teacher_courses WHERE teacher_id=? ORDER BY department, semester, shift, no'
);
$cStmt->execute([$teacherId]);
foreach ($cStmt->fetchAll() as $c) {
    if (!$c['department'] || !$c['semester'] || !$c['shift']) continue;
    $key = $c['department'].'||'.$c['semester'].'||'.$c['shift'];
    $classes[$key]['meta'] = [
        'department' => $c['department'],
        'semester'   => $c['semester'],
        'shift'      => $c['shift'],
    ];
    $classes[$key]['subjects'][] = $c['subject_name'];
}

// ── Load existing schedule slots ──────────────────────────────────
$slStmt = $pdo->prepare('SELECT * FROM teacher_schedules WHERE teacher_id=? ORDER BY day_of_week, time_start, id');
$slStmt->execute([$teacherId]);
$allSlots = $slStmt->fetchAll();

$byDay   = [];
$counts  = ['draft'=>0, 'submitted'=>0, 'approved'=>0, 'rejected'=>0];
$rejNotes = [];
foreach ($allSlots as $s) {
    $byDay[$s['day_of_week']][] = $s;
    $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
    if ($s['status'] === 'rejected' && $s['admin_note']) {
        $rejNotes[$s['admin_note']] = true;
    }
}
$hasEditable  = $counts['draft'] > 0 || $counts['rejected'] > 0;
$hasSubmitted = $counts['submitted'] > 0;

// JS data: classes → subjects map
$classJs = [];
foreach ($classes as $key => $cls) {
    $classJs[$key] = [
        'dept'     => $cls['meta']['department'],
        'sem'      => $cls['meta']['semester'],
        'shift'    => $cls['meta']['shift'],
        'subjects' => $cls['subjects'],
    ];
}

function statusChip(string $status): string {
    $map = [
        'draft'     => ['Draft',    'background:var(--surface-hover);color:var(--text-secondary);'],
        'submitted' => ['Pending',  'background:rgba(15,108,189,.12);color:#0f6cbd;border:1px solid rgba(15,108,189,.3);'],
        'approved'  => ['Approved', 'background:rgba(14,122,14,.12);color:#0e7a0e;border:1px solid rgba(14,122,14,.3);'],
        'rejected'  => ['Rejected', 'background:rgba(196,43,28,.12);color:#c42b1c;border:1px solid rgba(196,43,28,.3);'],
    ];
    [$lbl, $st] = $map[$status] ?? ['—',''];
    return '<span class="fluent-badge" style="font-size:10px;'.$st.'">'.$lbl.'</span>';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.slot-card  { border-radius:8px;padding:9px 11px;margin-bottom:8px;border:1px solid var(--border);background:var(--surface);position:relative; }
.day-col-h  { font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-tertiary);padding:4px 4px 8px;border-bottom:1px solid var(--border);margin-bottom:8px; }
.del-x      { position:absolute;top:6px;left:6px;width:18px;height:18px;border:none;background:none;color:#c42b1c;cursor:pointer;font-size:15px;line-height:1;border-radius:4px; }
.del-x:hover{ background:rgba(196,43,28,.1); }
</style>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex items-start justify-between mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">My Schedule</h1>
            <p class="fluent-caption mt-1">Build your weekly schedule, then submit it to the administration for approval.</p>
        </div>
        <div class="flex gap-2">
            <?php if ($hasEditable): ?>
            <form method="POST" onsubmit="return confirm('Submit your schedule to the administration for approval?');">
                <input type="hidden" name="action" value="submit">
                <button class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Submit for Approval
                </button>
            </form>
            <?php elseif ($hasSubmitted): ?>
            <form method="POST" onsubmit="return confirm('Recall your submission to edit it again?');">
                <input type="hidden" name="action" value="recall">
                <button class="fluent-btn" style="font-size:13px;">↩ Recall Submission</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="fluent-alert <?= $flash[0]==='ok' ? 'fluent-alert-success' : 'fluent-alert-danger' ?> mb-4" data-flash>
        <?= htmlspecialchars($flash[1]) ?>
    </div>
    <?php endif; ?>

    <!-- Status summary -->
    <?php if (!empty($allSlots)): ?>
    <div class="flex flex-wrap gap-2 mb-4 fluent-fade-in" style="animation-delay:20ms;">
        <?php foreach (['draft'=>'Draft','submitted'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$lbl): ?>
        <?php if ($counts[$k]): ?>
        <span class="fluent-badge"><?= $lbl ?>: <strong><?= $counts[$k] ?></strong></span>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Rejection notes -->
    <?php if (!empty($rejNotes)): ?>
    <div class="fluent-card p-4 mb-4 fluent-fade-in" style="border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
        <p class="fluent-label mb-1" style="color:#c42b1c;">Admin feedback on rejected slots:</p>
        <?php foreach (array_keys($rejNotes) as $note): ?>
        <p style="font-size:13px;color:var(--text-secondary);">• <?= htmlspecialchars($note) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 fluent-fade-in" style="animation-delay:40ms;">

        <!-- ── Add-slot form ── -->
        <div class="lg:col-span-1">
            <div class="fluent-card p-5" style="position:sticky;top:80px;">
                <h2 class="fluent-h2 mb-4">Add a Slot</h2>

                <?php if (empty($classes)): ?>
                <p style="font-size:13px;color:var(--text-tertiary);">
                    You have no assigned courses yet. The administration must assign your subjects first.
                </p>
                <?php else: ?>
                <form method="POST" class="space-y-3" id="slotForm">
                    <input type="hidden" name="action" value="add_slot">
                    <input type="hidden" name="department" id="fDept">
                    <input type="hidden" name="semester"   id="fSem">
                    <input type="hidden" name="shift"      id="fShift">

                    <div>
                        <label class="fluent-label block mb-1.5">Class *</label>
                        <div class="fluent-input">
                            <select id="classSel" required>
                                <option value="">— Select class —</option>
                                <?php foreach ($classes as $key => $cls): $m = $cls['meta']; ?>
                                <option value="<?= htmlspecialchars($key) ?>">
                                    <?= dept_label($pdo, $m['department']) ?> · <?= htmlspecialchars($m['semester']) ?> · <?= htmlspecialchars($m['shift']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Subject *</label>
                        <div class="fluent-input">
                            <select name="subject_name" id="subjSel" required disabled>
                                <option value="">— Select class first —</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Day *</label>
                        <div class="fluent-input">
                            <select name="day_of_week" required>
                                <option value="">— Select day —</option>
                                <?php foreach ($days as $num=>$name): ?>
                                <option value="<?= $num ?>"><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="fluent-label block mb-1.5">Start</label>
                            <div class="fluent-input"><input type="time" name="time_start"></div>
                        </div>
                        <div>
                            <label class="fluent-label block mb-1.5">End</label>
                            <div class="fluent-input"><input type="time" name="time_end"></div>
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Room</label>
                        <div class="fluent-input">
                            <input type="text" name="room" placeholder="e.g. Room 101">
                        </div>
                    </div>

                    <button type="submit" class="fluent-btn-accent fluent-btn w-full" style="margin-top:4px;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Slot
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Weekly grid ── -->
        <div class="lg:col-span-2">
            <?php if (empty($allSlots)): ?>
            <div class="fluent-card p-12 text-center">
                <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p style="color:var(--text-tertiary);">No slots yet. Use the form to start building your weekly schedule.</p>
            </div>
            <?php else: ?>
            <div class="fluent-card p-4" style="overflow-x:auto;">
                <div style="display:grid;grid-template-columns:repeat(6,minmax(150px,1fr));gap:12px;min-width:920px;">
                    <?php foreach ($days as $dayNum => $dayName): ?>
                    <div>
                        <div class="day-col-h"><?= $dayName ?></div>
                        <?php if (!empty($byDay[$dayNum])): ?>
                            <?php foreach ($byDay[$dayNum] as $sl): ?>
                            <?php $editable = in_array($sl['status'], ['draft','rejected']); ?>
                            <div class="slot-card" style="<?= $sl['status']==='approved' ? 'border-color:rgba(14,122,14,.4);' : ($sl['status']==='rejected' ? 'border-color:rgba(196,43,28,.4);' : '') ?>">
                                <?php if ($editable): ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Remove this slot?');">
                                    <input type="hidden" name="action" value="delete_slot">
                                    <input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
                                    <button type="submit" class="del-x" title="Remove">&times;</button>
                                </form>
                                <?php endif; ?>

                                <div style="<?= $editable ? 'padding-left:18px;' : '' ?>">
                                    <?php if ($sl['time_start']): ?>
                                    <p style="font-size:10px;font-weight:600;color:var(--text-tertiary);margin-bottom:3px;">
                                        <?= substr($sl['time_start'],0,5) ?><?= $sl['time_end'] ? ' – '.substr($sl['time_end'],0,5) : '' ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="font-size:13px;font-weight:700;margin-bottom:3px;"><?= htmlspecialchars($sl['subject_name']) ?></p>
                                    <p style="font-size:11px;color:var(--accent);margin-bottom:2px;">
                                        <?= dept_label($pdo, $sl['department']) ?> · <?= htmlspecialchars($sl['semester']) ?>
                                    </p>
                                    <p style="font-size:10px;color:var(--text-tertiary);margin-bottom:4px;"><?= htmlspecialchars($sl['shift']) ?></p>
                                    <?php if ($sl['room']): ?>
                                    <p style="font-size:11px;color:var(--text-tertiary);margin-bottom:4px;">Room: <?= htmlspecialchars($sl['room']) ?></p>
                                    <?php endif; ?>
                                    <?= statusChip($sl['status']) ?>
                                </div>
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
        </div>
    </div>
</main>

<script>
var CLASS_DATA = <?= json_encode($classJs, JSON_UNESCAPED_UNICODE) ?>;
(function () {
    var classSel = document.getElementById('classSel');
    if (!classSel) return;
    var subjSel = document.getElementById('subjSel');

    classSel.addEventListener('change', function () {
        var key = this.value;
        document.getElementById('fDept').value  = key ? CLASS_DATA[key].dept  : '';
        document.getElementById('fSem').value   = key ? CLASS_DATA[key].sem   : '';
        document.getElementById('fShift').value = key ? CLASS_DATA[key].shift : '';

        subjSel.innerHTML = '';
        if (!key) {
            subjSel.disabled = true;
            subjSel.innerHTML = '<option value="">— Select class first —</option>';
            return;
        }
        subjSel.disabled = false;
        subjSel.innerHTML = '<option value="">— Select subject —</option>';
        CLASS_DATA[key].subjects.forEach(function (s) {
            var o = document.createElement('option');
            o.value = s; o.textContent = s;
            subjSel.appendChild(o);
        });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
