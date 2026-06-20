<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Exam Schedule — ' . SITE_NAME;
$error = '';

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = get_departments($pdo);
$shifts = ['06:00 – 09:00', '09:00 – 12:00', '01:00 – 04:00'];

// Teachers for invigilator select
$allTeachers = $pdo->query(
    'SELECT t.id, u.name, t.teacher_no FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE u.status=1 ORDER BY u.name'
)->fetchAll();

// Editing existing?
$editId = (int)($_GET['id'] ?? 0);
$rec = [];
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM exam_schedules WHERE id=?');
    $s->execute([$editId]);
    $rec = $s->fetch() ?: [];
    if (!$rec) { header('Location: index.php?tab=exam_schedule'); exit; }
}

// ── POST handler ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examType   = $_POST['exam_type']    ?? 'midterm';
    $subject    = trim($_POST['subject_name']  ?? '');
    $dept       = trim($_POST['department']    ?? '');
    $sem        = trim($_POST['semester']      ?? '');
    $shift      = trim($_POST['shift']         ?? '');
    $examDate   = trim($_POST['exam_date']     ?? '');
    $startTime  = trim($_POST['start_time']    ?? '') ?: null;
    $endTime    = trim($_POST['end_time']      ?? '') ?: null;
    $room       = trim($_POST['room']          ?? '') ?: null;
    $inv1       = (int)($_POST['invigilator_id']  ?? 0) ?: null;
    $inv2       = (int)($_POST['invigilator2_id'] ?? 0) ?: null;
    $notes      = trim($_POST['notes']         ?? '') ?: null;
    $id         = (int)($_POST['id'] ?? 0);

    if (!$subject || !$examDate || !in_array($examType, ['midterm','final'])) {
        $error = 'Subject and date are required.';
    } else {
        if ($id) {
            $pdo->prepare(
                'UPDATE exam_schedules SET exam_type=?, subject_name=?, department=?, semester=?,
                 shift=?, exam_date=?, start_time=?, end_time=?, room=?,
                 invigilator_id=?, invigilator2_id=?, notes=? WHERE id=?'
            )->execute([$examType, $subject, $dept ?: null, $sem ?: null, $shift ?: null,
                        $examDate, $startTime, $endTime, $room, $inv1, $inv2, $notes, $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO exam_schedules
                 (exam_type, subject_name, department, semester, shift, exam_date,
                  start_time, end_time, room, invigilator_id, invigilator2_id, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$examType, $subject, $dept ?: null, $sem ?: null, $shift ?: null,
                        $examDate, $startTime, $endTime, $room, $inv1, $inv2, $notes]);
        }
        log_activity($pdo, 'exam_schedule_saved', ($id ? 'Updated' : 'Created') . " exam: $subject");
        header('Location: index.php?tab=exam_schedule&saved=1');
        exit;
    }

    // Re-populate $rec on error
    $rec = compact('examType','subject','dept','sem','shift','examDate','startTime','endTime','room','inv1','inv2','notes');
    $rec['exam_type']       = $examType;
    $rec['subject_name']    = $subject;
    $rec['department']      = $dept;
    $rec['semester']        = $sem;
    $rec['invigilator_id']  = $inv1;
    $rec['invigilator2_id'] = $inv2;
    $rec['exam_date']       = $examDate;
    $rec['start_time']      = $startTime;
    $rec['end_time']        = $endTime;
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex items-center gap-3 mb-5 fluent-fade-in">
        <a href="index.php?tab=exam_schedule" class="fluent-btn" style="padding:4px 10px;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="fluent-h1"><?= $editId ? 'Edit' : 'Add' ?> Exam Schedule</h1>
            <p class="fluent-caption mt-0.5">Schedule an exam for a class with room and invigilators.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="fluent-alert fluent-alert-danger mb-5"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="max-w-2xl space-y-5 fluent-fade-in" style="animation-delay:40ms;">
        <input type="hidden" name="id" value="<?= $editId ?>">

        <div class="fluent-card p-6 space-y-4">
            <h2 class="fluent-h2">Exam Details</h2>

            <!-- Exam type + subject -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Exam Type *</label>
                    <div class="fluent-input">
                        <select name="exam_type" required>
                            <option value="midterm" <?= ($rec['exam_type'] ?? '') === 'midterm' ? 'selected' : '' ?>>Midterm (20%)</option>
                            <option value="final"   <?= ($rec['exam_type'] ?? '') === 'final'   ? 'selected' : '' ?>>Final (80%)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Subject Name *</label>
                    <div class="fluent-input">
                        <input type="text" name="subject_name" required
                               value="<?= htmlspecialchars($rec['subject_name'] ?? '') ?>"
                               placeholder="e.g. Anatomy">
                    </div>
                </div>
            </div>

            <!-- Department → Semester cascade -->
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Department</label>
                    <div class="fluent-input">
                        <select name="department" id="deptSel">
                            <option value="">— Any —</option>
                            <?php foreach ($allDepts as $d): ?>
                            <option value="<?= htmlspecialchars($d['name_en']) ?>"
                                    data-max="<?= (int)$d['max_semesters'] ?>"
                                    <?= ($rec['department'] ?? '') === $d['name_en'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name_en']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Semester</label>
                    <div class="fluent-input">
                        <select name="semester" id="semSel">
                            <option value="">— Any —</option>
                            <?php
                            $selSem = $rec['semester'] ?? '';
                            for ($i=1;$i<=6;$i++):
                                $suf = $i<=3 ? ['','st','nd','rd'][$i] : 'th';
                                $label = $i.$suf.' Semester';
                            ?>
                            <option <?= $selSem===$label?'selected':'' ?>><?= $label ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Shift</label>
                    <div class="fluent-input">
                        <select name="shift">
                            <option value="">— Any —</option>
                            <?php foreach ($shifts as $sh): ?>
                            <option <?= ($rec['shift'] ?? '') === $sh ? 'selected' : '' ?>><?= $sh ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="fluent-card p-6 space-y-4">
            <h2 class="fluent-h2">Date, Time &amp; Room</h2>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Exam Date *</label>
                    <div class="fluent-input">
                        <input type="date" name="exam_date" required
                               value="<?= htmlspecialchars($rec['exam_date'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Start Time</label>
                    <div class="fluent-input">
                        <input type="time" name="start_time"
                               value="<?= htmlspecialchars(substr($rec['start_time'] ?? '', 0, 5)) ?>">
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">End Time</label>
                    <div class="fluent-input">
                        <input type="time" name="end_time"
                               value="<?= htmlspecialchars(substr($rec['end_time'] ?? '', 0, 5)) ?>">
                    </div>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Room / Hall</label>
                <div class="fluent-input">
                    <input type="text" name="room"
                           value="<?= htmlspecialchars($rec['room'] ?? '') ?>"
                           placeholder="e.g. Hall A, Room 101">
                </div>
            </div>
        </div>

        <div class="fluent-card p-6 space-y-4">
            <h2 class="fluent-h2">Invigilators</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Invigilator 1</label>
                    <div class="fluent-input">
                        <select name="invigilator_id">
                            <option value="">— None —</option>
                            <?php foreach ($allTeachers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                    <?= ($rec['invigilator_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                                <?php if ($t['teacher_no']): ?>(<?= htmlspecialchars($t['teacher_no']) ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Invigilator 2</label>
                    <div class="fluent-input">
                        <select name="invigilator2_id">
                            <option value="">— None —</option>
                            <?php foreach ($allTeachers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                    <?= ($rec['invigilator2_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                                <?php if ($t['teacher_no']): ?>(<?= htmlspecialchars($t['teacher_no']) ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <label class="fluent-label block mb-1.5">Notes</label>
                <div class="fluent-input" style="align-items:flex-start;">
                    <textarea name="notes" rows="2"
                              style="resize:vertical;border:none;background:transparent;width:100%;outline:none;font-size:13px;"
                              placeholder="Any additional notes…"><?= htmlspecialchars($rec['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:14px;padding:10px 20px;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <?= $editId ? 'Update' : 'Save' ?> Exam
            </button>
            <a href="index.php?tab=exam_schedule" class="fluent-btn" style="font-size:14px;padding:10px 20px;">Cancel</a>
        </div>
    </form>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
