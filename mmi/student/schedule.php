<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/departments.php';
require_role('student');

$pageTitle = 'My Schedule — ' . SITE_NAME;

$user = current_user();
$stmt = $pdo->prepare('SELECT department, semester, shift FROM students WHERE user_id=?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$slots    = [];
$schedule = null;
$missingProfile = !$profile || !$profile['department'] || !$profile['semester'] || !$profile['shift'];

if (!$missingProfile) {
    $stmt = $pdo->prepare('SELECT * FROM schedules WHERE department=? AND semester=? AND shift=?');
    $stmt->execute([$profile['department'], $profile['semester'], $profile['shift']]);
    $schedule = $stmt->fetch();
    if ($schedule) {
        $s2 = $pdo->prepare('SELECT * FROM schedule_slots WHERE schedule_id=? ORDER BY day_of_week, time_start');
        $s2->execute([$schedule['id']]);
        $slots = $s2->fetchAll();
    }
}

$days  = [1=>'Saturday',2=>'Sunday',3=>'Monday',4=>'Tuesday',5=>'Wednesday',6=>'Thursday'];
$byDay = [];
foreach ($slots as $s) $byDay[$s['day_of_week']][] = $s;

// Upcoming exams for this student's class
$upcomingExams = [];
if (!$missingProfile) {
    try {
        $ex = $pdo->prepare(
            'SELECT es.*,
                    u1.name AS inv1_name,
                    u2.name AS inv2_name
             FROM exam_schedules es
             LEFT JOIN teachers t1 ON t1.id = es.invigilator_id
             LEFT JOIN users    u1 ON u1.id = t1.user_id
             LEFT JOIN teachers t2 ON t2.id = es.invigilator2_id
             LEFT JOIN users    u2 ON u2.id = t2.user_id
             WHERE es.department = ? AND es.semester = ? AND es.shift = ?
               AND es.exam_date >= CURDATE()
             ORDER BY es.exam_date ASC, es.start_time ASC'
        );
        $ex->execute([$profile['department'], $profile['semester'], $profile['shift']]);
        $upcomingExams = $ex->fetchAll();
    } catch (Exception $e) {}
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.slot-card { border-radius:8px;padding:10px 12px;margin-bottom:8px;border:1px solid var(--border);background:var(--surface); }
.day-header { font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-tertiary);padding:4px 4px 8px;border-bottom:1px solid var(--border);margin-bottom:8px; }
</style>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Schedule</h1>
        <p class="fluent-caption mt-1">Class timetable and upcoming exams.</p>
    </div>

    <?php if ($missingProfile): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">Your class information is not set up yet. Please contact an administrator.</p>
    </div>
    <?php elseif (!$schedule): ?>
    <div class="fluent-card px-5 py-3 mb-5 fluent-fade-in" style="background:color-mix(in srgb,var(--accent) 6%,var(--surface));">
        <p class="fluent-body" style="font-size:13px;">
            <span style="font-weight:600;"><?= dept_label($pdo, $profile['department']) ?></span>
            &nbsp;·&nbsp;<?= htmlspecialchars($profile['semester']) ?>
            &nbsp;·&nbsp;<?= htmlspecialchars($profile['shift']) ?>
        </p>
    </div>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <p class="fluent-body" style="color:var(--text-tertiary);">No schedule has been created for your class yet.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card px-5 py-3 mb-4 fluent-fade-in" style="background:color-mix(in srgb,var(--accent) 6%,var(--surface));">
        <div class="flex items-center gap-3">
            <span class="fluent-body" style="font-weight:600;"><?= dept_label($pdo, $profile['department']) ?> · <?= htmlspecialchars($profile['semester']) ?> · <?= htmlspecialchars($profile['shift']) ?></span>
            <span class="fluent-badge"><?= count($slots) ?> slot<?= count($slots) != 1 ? 's' : '' ?></span>
        </div>
    </div>
    <div class="fluent-card p-4 fluent-fade-in" style="animation-delay:60ms;overflow-x:auto;">
        <div style="display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:12px;min-width:840px;">
            <?php foreach ($days as $dayNum => $dayName): ?>
            <div>
                <div class="day-header"><?= $dayName ?></div>
                <?php if (!empty($byDay[$dayNum])): ?>
                    <?php foreach ($byDay[$dayNum] as $sl): ?>
                    <div class="slot-card">
                        <p style="font-size:10px;font-weight:600;color:var(--text-tertiary);margin-bottom:4px;"><?= substr($sl['time_start'],0,5) ?> – <?= substr($sl['time_end'],0,5) ?></p>
                        <p style="font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:2px;"><?= htmlspecialchars($sl['subject'] ?? '—') ?></p>
                        <?php if ($sl['teacher']): ?><p style="font-size:11px;color:var(--accent);"><?= htmlspecialchars($sl['teacher']) ?></p><?php endif; ?>
                        <?php if ($sl['room']): ?><p style="font-size:11px;color:var(--text-tertiary);">Room: <?= htmlspecialchars($sl['room']) ?></p><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <p style="font-size:12px;color:var(--text-tertiary);padding:8px 4px;">No classes</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Upcoming Exams ──────────────────────────────────────── -->
    <?php if (!$missingProfile): ?>
    <div class="mt-6 fluent-fade-in" style="animation-delay:100ms;">
        <h2 class="fluent-h2 mb-3">Upcoming Exams</h2>

        <?php if (empty($upcomingExams)): ?>
        <div class="fluent-card p-8 text-center">
            <p style="color:var(--text-tertiary);font-size:14px;">No upcoming exams scheduled for your class.</p>
        </div>
        <?php else: ?>
        <div class="fluent-card overflow-hidden">
            <table class="fluent-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Invigilator(s)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($upcomingExams as $ex): ?>
                <?php $daysLeft = (int)ceil((strtotime($ex['exam_date']) - time()) / 86400); ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($ex['subject_name']) ?></td>
                    <td>
                        <span class="fluent-badge <?= $ex['exam_type']==='midterm' ? '' : 'fluent-badge-success' ?>"
                              style="text-transform:capitalize;"><?= $ex['exam_type'] ?></span>
                    </td>
                    <td style="white-space:nowrap;">
                        <span style="font-weight:600;"><?= date('d M Y', strtotime($ex['exam_date'])) ?></span>
                        <?php if ($daysLeft <= 3): ?>
                        <span class="fluent-badge" style="background:rgba(196,43,28,.12);color:#c42b1c;border:none;font-size:10px;margin-left:4px;">
                            <?= $daysLeft === 0 ? 'Today' : ($daysLeft === 1 ? 'Tomorrow' : "In $daysLeft days") ?>
                        </span>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--text-tertiary);margin-left:4px;">in <?= $daysLeft ?> days</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-secondary);white-space:nowrap;">
                        <?= $ex['start_time'] ? substr($ex['start_time'],0,5) : '—' ?>
                        <?= $ex['end_time']   ? ' – '.substr($ex['end_time'],0,5) : '' ?>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($ex['room'] ?? '—') ?></td>
                    <td style="font-size:12px;color:var(--text-secondary);">
                        <?php
                        $invs = array_filter([$ex['inv1_name'], $ex['inv2_name']]);
                        echo $invs ? htmlspecialchars(implode(', ', $invs)) : '—';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
