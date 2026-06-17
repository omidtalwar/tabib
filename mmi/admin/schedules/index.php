<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Schedules — ' . SITE_NAME;

$schedules = $pdo->query(
    'SELECT s.*, u.name AS created_by_name,
            (SELECT COUNT(*) FROM schedule_slots ss WHERE ss.schedule_id = s.id) AS slot_count
     FROM schedules s
     JOIN users u ON u.id = s.created_by
     ORDER BY s.department, s.semester, s.shift'
)->fetchAll();

// Distinct classes from teacher_courses for "New Schedule" modal
$classes = $pdo->query(
    'SELECT DISTINCT department, semester, shift FROM teacher_courses
     WHERE department IS NOT NULL AND semester IS NOT NULL AND shift IS NOT NULL
     ORDER BY department, semester, shift'
)->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex items-center justify-between mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Schedules</h1>
            <p class="fluent-caption mt-1">Create and manage weekly class timetables.</p>
        </div>
        <div class="flex gap-2">
            <a href="approvals.php" class="fluent-btn" style="font-size:13px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Teacher Approvals
            </a>
            <button id="btnNew" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Schedule
            </button>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="fluent-card px-4 py-3 mb-4 fluent-fade-in"
         style="border-left:3px solid <?= $flash['type'] === 'success' ? '#0e7a0e' : '#c42b1c' ?>;background:color-mix(in srgb,<?= $flash['type'] === 'success' ? '#0e7a0e' : '#c42b1c' ?> 8%,var(--surface));">
        <p style="font-size:13px;color:var(--text-primary);"><?= htmlspecialchars($flash['msg']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (empty($schedules)): ?>
    <div class="fluent-card p-12 text-center fluent-fade-in" style="animation-delay:60ms;">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No schedules yet. Click <strong>New Schedule</strong> to create one.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Shift</th>
                    <th style="width:90px;">Slots</th>
                    <th>Created By</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($schedules as $sc): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($sc['department']) ?></td>
                <td><?= htmlspecialchars($sc['semester']) ?></td>
                <td><span class="fluent-badge"><?= htmlspecialchars($sc['shift']) ?></span></td>
                <td>
                    <span class="fluent-badge fluent-badge-success"><?= (int)$sc['slot_count'] ?> slot<?= $sc['slot_count'] != 1 ? 's' : '' ?></span>
                </td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($sc['created_by_name']) ?></td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="manage.php?id=<?= (int)$sc['id'] ?>"
                           class="fluent-btn" style="padding:3px 10px;font-size:12px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 30%,transparent);">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Manage
                        </a>
                        <form method="post" action="delete.php"
                              onsubmit="return confirm('Delete schedule for <?= htmlspecialchars(addslashes($sc['department'] . ' ' . $sc['semester'])) ?>?');">
                            <input type="hidden" name="id" value="<?= (int)$sc['id'] ?>">
                            <button type="submit" class="fluent-btn"
                                    style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:color-mix(in srgb,#c42b1c 30%,transparent);">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
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
    <?php endif; ?>
</main>

<!-- New Schedule Modal -->
<div id="newModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
     style="background:rgba(0,0,0,0.35);backdrop-filter:blur(4px);">
    <div class="fluent-card w-full max-w-sm mx-4 fluent-fade-in" style="box-shadow:var(--shadow-lg);">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid var(--border);">
            <h2 class="fluent-h3">New Schedule</h2>
            <button id="closeNew" class="w-8 h-8 rounded-md flex items-center justify-center"
                    style="color:var(--text-tertiary);"
                    onmouseover="this.style.background='var(--hover)'"
                    onmouseout="this.style.background='transparent'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="get" action="manage.php" class="px-6 py-5 space-y-4">
            <div>
                <label class="fluent-label block mb-1.5">Department</label>
                <div class="fluent-input">
                    <select name="department" required>
                        <option value="">— Select department —</option>
                        <?php
                        $depts = array_unique(array_column($classes, 'department'));
                        foreach ($depts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="fluent-label block mb-1.5">Semester</label>
                <div class="fluent-input">
                    <select name="semester" required>
                        <option value="">— Select semester —</option>
                        <?php
                        $sems = array_unique(array_column($classes, 'semester'));
                        foreach ($sems as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="fluent-label block mb-1.5">Shift</label>
                <div class="fluent-input">
                    <select name="shift" required>
                        <option value="">— Select shift —</option>
                        <?php
                        $shfts = array_unique(array_column($classes, 'shift'));
                        foreach ($shfts as $sh): ?>
                        <option value="<?= htmlspecialchars($sh) ?>"><?= htmlspecialchars($sh) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                    Open / Create
                </button>
                <button type="button" id="cancelNew" class="fluent-btn" style="font-size:13px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('btnNew').addEventListener('click', () => document.getElementById('newModal').classList.remove('hidden'));
function closeNew() { document.getElementById('newModal').classList.add('hidden'); }
document.getElementById('closeNew').addEventListener('click', closeNew);
document.getElementById('cancelNew').addEventListener('click', closeNew);
document.getElementById('newModal').addEventListener('click', e => { if (e.target === document.getElementById('newModal')) closeNew(); });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
