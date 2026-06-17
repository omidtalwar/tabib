<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$user = current_user();

// ── Find-or-create from dept/sem/shift params ─────────────────────
if (isset($_GET['department'], $_GET['semester'], $_GET['shift'])) {
    $dept  = trim($_GET['department']);
    $sem   = trim($_GET['semester']);
    $shift = trim($_GET['shift']);
    if ($dept && $sem && $shift) {
        $stmt = $pdo->prepare('SELECT id FROM schedules WHERE department=? AND semester=? AND shift=?');
        $stmt->execute([$dept, $sem, $shift]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            header('Location: manage.php?id=' . (int)$existing); exit;
        }
        $pdo->prepare('INSERT INTO schedules (department,semester,shift,created_by) VALUES (?,?,?,?)')
            ->execute([$dept, $sem, $shift, $user['id']]);
        header('Location: manage.php?id=' . $pdo->lastInsertId()); exit;
    }
    header('Location: index.php'); exit;
}

// ── Load schedule ─────────────────────────────────────────────────
$scheduleId = (int)($_GET['id'] ?? 0);
if (!$scheduleId) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM schedules WHERE id=?');
$stmt->execute([$scheduleId]);
$schedule = $stmt->fetch();
if (!$schedule) { header('Location: index.php'); exit; }

// ── Load slots ────────────────────────────────────────────────────
$slots = $pdo->prepare(
    'SELECT * FROM schedule_slots WHERE schedule_id=? ORDER BY day_of_week, time_start'
);
$slots->execute([$scheduleId]);
$slots = $slots->fetchAll();

// ── Subjects/teachers autocomplete from this class ────────────────
$classSubjects = $pdo->prepare(
    'SELECT DISTINCT tc.subject_name, u.name AS teacher_name
     FROM teacher_courses tc
     JOIN teachers t ON t.id = tc.teacher_id
     JOIN users u    ON u.id = t.user_id
     WHERE tc.department=? AND tc.semester=? AND tc.shift=?
     ORDER BY tc.no, tc.id'
);
$classSubjects->execute([$schedule['department'], $schedule['semester'], $schedule['shift']]);
$classSubjects = $classSubjects->fetchAll();

// Group slots by day
$byDay = [];
foreach ($slots as $s) $byDay[$s['day_of_week']][] = $s;

$days = [1=>'Saturday',2=>'Sunday',3=>'Monday',4=>'Tuesday',5=>'Wednesday',6=>'Thursday'];

$pageTitle = 'Manage Schedule — ' . SITE_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<style>
.slot-card {
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    position: relative;
}
.slot-card:hover .slot-actions { opacity: 1; }
.slot-actions {
    opacity: 0;
    transition: opacity .15s;
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 4px;
}
.day-col { min-width: 0; }
.day-header {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--text-tertiary);
    padding: 6px 4px 8px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 8px;
}
</style>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Header -->
    <div class="flex items-center justify-between mb-5 fluent-fade-in">
        <div class="flex items-center gap-3">
            <a href="index.php" class="fluent-btn" style="padding:4px 10px;font-size:12px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
            </a>
            <div>
                <h1 class="fluent-h1">
                    <?= htmlspecialchars($schedule['department']) ?>
                    &nbsp;·&nbsp;<?= htmlspecialchars($schedule['semester']) ?>
                    &nbsp;·&nbsp;<?= htmlspecialchars($schedule['shift']) ?>
                </h1>
                <p class="fluent-caption mt-0.5">Weekly schedule — <?= count($slots) ?> slot<?= count($slots) != 1 ? 's' : '' ?></p>
            </div>
        </div>
        <button id="btnAddSlot" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Slot
        </button>
    </div>

    <!-- Timetable grid -->
    <div class="fluent-card p-4 fluent-fade-in" style="animation-delay:60ms;overflow-x:auto;">
        <div style="display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:12px;min-width:840px;">
            <?php foreach ($days as $dayNum => $dayName): ?>
            <div class="day-col">
                <div class="day-header"><?= $dayName ?></div>
                <?php if (!empty($byDay[$dayNum])): ?>
                    <?php foreach ($byDay[$dayNum] as $sl): ?>
                    <div class="slot-card" data-id="<?= $sl['id'] ?>">
                        <div class="slot-actions">
                            <button type="button" class="btn-edit-slot"
                                    data-slot='<?= htmlspecialchars(json_encode($sl), ENT_QUOTES) ?>'
                                    title="Edit"
                                    style="width:22px;height:22px;border-radius:4px;border:1px solid var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;cursor:pointer;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent);">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button type="button" class="btn-delete-slot" data-id="<?= $sl['id'] ?>" title="Delete"
                                    style="width:22px;height:22px;border-radius:4px;border:1px solid var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;cursor:pointer;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#c42b1c;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        <p style="font-size:10px;font-weight:600;color:var(--text-tertiary);margin-bottom:4px;">
                            <?= substr($sl['time_start'],0,5) ?> – <?= substr($sl['time_end'],0,5) ?>
                        </p>
                        <p style="font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:2px;">
                            <?= htmlspecialchars($sl['subject'] ?? '—') ?>
                        </p>
                        <?php if ($sl['teacher']): ?>
                        <p style="font-size:11px;color:var(--accent);">
                            <?= htmlspecialchars($sl['teacher']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($sl['room']): ?>
                        <p style="font-size:11px;color:var(--text-tertiary);">Room: <?= htmlspecialchars($sl['room']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <p style="font-size:12px;color:var(--text-tertiary);padding:8px 4px;">No slots</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- ── Add / Edit Slot Modal ──────────────────────────────────────── -->
<div id="slotModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
     style="background:rgba(0,0,0,0.35);backdrop-filter:blur(4px);">
    <div class="fluent-card w-full max-w-md mx-4 fluent-fade-in" style="box-shadow:var(--shadow-lg);">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid var(--border);">
            <h2 class="fluent-h3" id="slotModalTitle">Add Slot</h2>
            <button id="closeSlotModal" class="w-8 h-8 rounded-md flex items-center justify-center"
                    style="color:var(--text-tertiary);"
                    onmouseover="this.style.background='var(--hover)'"
                    onmouseout="this.style.background='transparent'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <input type="hidden" id="slotId" value="0">

            <div>
                <label class="fluent-label block mb-1.5">Day</label>
                <div class="fluent-input">
                    <select id="slotDay">
                        <?php foreach ($days as $n => $name): ?>
                        <option value="<?= $n ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="fluent-label block mb-1.5">Start Time</label>
                    <div class="fluent-input">
                        <input type="time" id="slotStart" value="08:00">
                    </div>
                </div>
                <div class="flex-1">
                    <label class="fluent-label block mb-1.5">End Time</label>
                    <div class="fluent-input">
                        <input type="time" id="slotEnd" value="09:30">
                    </div>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Subject</label>
                <div class="fluent-input">
                    <input type="text" id="slotSubject" placeholder="e.g. Mathematics" list="subjectList">
                </div>
                <datalist id="subjectList">
                    <?php foreach ($classSubjects as $cs): ?>
                    <option value="<?= htmlspecialchars($cs['subject_name']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Teacher</label>
                <div class="fluent-input">
                    <input type="text" id="slotTeacher" placeholder="e.g. Dr. Ahmad" list="teacherList">
                </div>
                <datalist id="teacherList">
                    <?php foreach ($classSubjects as $cs): ?>
                    <option value="<?= htmlspecialchars($cs['teacher_name']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Room <span style="font-weight:400;color:var(--text-tertiary);">(optional)</span></label>
                <div class="fluent-input">
                    <input type="text" id="slotRoom" placeholder="e.g. Room 101">
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 px-6 py-4" style="border-top:1px solid var(--border);">
            <button id="btnSaveSlot" class="fluent-btn-accent fluent-btn" style="font-size:13px;">Save Slot</button>
            <button id="cancelSlot" class="fluent-btn" style="font-size:13px;">Cancel</button>
            <span id="slotError" style="font-size:12px;color:#c42b1c;margin-left:auto;"></span>
        </div>
    </div>
</div>

<script>
const SCHEDULE_ID = <?= $scheduleId ?>;
const DAYS = <?= json_encode($days) ?>;

// ── Modal helpers ─────────────────────────────────────────────────
const modal = document.getElementById('slotModal');
function openModal(editData = null) {
    document.getElementById('slotError').textContent = '';
    if (editData) {
        document.getElementById('slotModalTitle').textContent = 'Edit Slot';
        document.getElementById('slotId').value      = editData.id;
        document.getElementById('slotDay').value     = editData.day_of_week;
        document.getElementById('slotStart').value   = editData.time_start.substring(0,5);
        document.getElementById('slotEnd').value     = editData.time_end.substring(0,5);
        document.getElementById('slotSubject').value = editData.subject || '';
        document.getElementById('slotTeacher').value = editData.teacher || '';
        document.getElementById('slotRoom').value    = editData.room || '';
    } else {
        document.getElementById('slotModalTitle').textContent = 'Add Slot';
        document.getElementById('slotId').value      = '0';
        document.getElementById('slotDay').value     = '1';
        document.getElementById('slotStart').value   = '08:00';
        document.getElementById('slotEnd').value     = '09:30';
        document.getElementById('slotSubject').value = '';
        document.getElementById('slotTeacher').value = '';
        document.getElementById('slotRoom').value    = '';
    }
    modal.classList.remove('hidden');
}
function closeModal() { modal.classList.add('hidden'); }

document.getElementById('btnAddSlot').addEventListener('click', () => openModal());
document.getElementById('closeSlotModal').addEventListener('click', closeModal);
document.getElementById('cancelSlot').addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ── Edit buttons ──────────────────────────────────────────────────
document.querySelectorAll('.btn-edit-slot').forEach(btn => {
    btn.addEventListener('click', () => openModal(JSON.parse(btn.dataset.slot)));
});

// ── Delete slots ──────────────────────────────────────────────────
document.querySelectorAll('.btn-delete-slot').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (!confirm('Delete this slot?')) return;
        const res = await fetch('delete_slot.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id: parseInt(this.dataset.id), schedule_id: SCHEDULE_ID })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Failed to delete.');
    });
});

// ── Save slot ─────────────────────────────────────────────────────
document.getElementById('btnSaveSlot').addEventListener('click', async function () {
    const start   = document.getElementById('slotStart').value;
    const end     = document.getElementById('slotEnd').value;
    const subject = document.getElementById('slotSubject').value.trim();
    if (!start || !end || !subject) {
        document.getElementById('slotError').textContent = 'Start time, end time and subject are required.';
        return;
    }
    if (start >= end) {
        document.getElementById('slotError').textContent = 'End time must be after start time.';
        return;
    }
    const payload = {
        id:          parseInt(document.getElementById('slotId').value),
        schedule_id: SCHEDULE_ID,
        day_of_week: parseInt(document.getElementById('slotDay').value),
        time_start:  start,
        time_end:    end,
        subject:     subject,
        teacher:     document.getElementById('slotTeacher').value.trim(),
        room:        document.getElementById('slotRoom').value.trim(),
    };
    const res  = await fetch('save_slot.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) location.reload();
    else document.getElementById('slotError').textContent = data.error || 'Failed to save.';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
