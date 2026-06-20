<?php
$role    = $_SESSION['role'] ?? '';
$baseUrl = BASE_URL;

// Pending exam approval count (admin only)
$pendingApprovals = 0;
$pendingSchedules = 0;
if ($role === 'admin') {
    try {
        $r = $pdo->query('SELECT COUNT(*) FROM exam_submissions WHERE status = "submitted"');
        $pendingApprovals = (int)$r->fetchColumn();
    } catch (Exception $e) { /* table may not exist yet */ }
    try {
        $r = $pdo->query('SELECT COUNT(DISTINCT teacher_id) FROM teacher_schedules WHERE status = "submitted"');
        $pendingSchedules = (int)$r->fetchColumn();
    } catch (Exception $e) { /* table may not exist yet */ }
    $pendingExamPapers = 0;
    try {
        $r = $pdo->query('SELECT COUNT(*) FROM exam_papers WHERE status = "submitted"');
        $pendingExamPapers = (int)$r->fetchColumn();
    } catch (Exception $e) { /* table may not exist yet */ }
}

// New materials count for students (since 7 days)
$newMaterialsCount = 0;
if ($role === 'student' && isset($_SESSION['user_id'])) {
    try {
        $st = $pdo->prepare('SELECT department, semester, shift FROM students WHERE user_id = ?');
        $st->execute([(int)$_SESSION['user_id']]);
        $stu = $st->fetch();
        if ($stu && $stu['department']) {
            $since = date('Y-m-d H:i:s', time() - 7 * 86400);
            $st2 = $pdo->prepare(
                'SELECT COUNT(*) FROM materials m
                 LEFT JOIN teacher_courses tc ON tc.id = m.course_id
                 WHERE (m.course_id IS NULL OR (tc.department = ? AND tc.semester = ? AND tc.shift = ?))
                   AND m.created_at > ?'
            );
            $st2->execute([$stu['department'], $stu['semester'], $stu['shift'], $since]);
            $newMaterialsCount = (int)$st2->fetchColumn();
        }
    } catch (Exception $e) {}
}

$adminNav = [
    ['label' => 'Dashboard', 'href' => $baseUrl . '/admin/', 'icon' => 'dashboard'],
    ['label' => 'Teachers',  'icon' => 'teachers', 'children' => [
        ['label' => 'All Teachers', 'href' => $baseUrl . '/admin/teachers/'],
        ['label' => 'Add Teacher',  'href' => $baseUrl . '/admin/teachers/add.php'],
    ]],
    ['label' => 'Students',  'icon' => 'students', 'children' => [
        ['label' => 'All Students', 'href' => $baseUrl . '/admin/students/'],
        ['label' => 'Add Student',  'href' => $baseUrl . '/admin/students/add.php'],
    ]],
    ['label' => 'Subjects',  'href' => $baseUrl . '/admin/subjects/', 'icon' => 'subjects'],
    ['label' => 'Exam Center', 'icon' => 'exam_scores', 'children' => [
        ['label' => 'Scores & Results', 'href' => $baseUrl . '/admin/exam_scores/'],
        ['label' => 'Exam Papers',      'href' => $baseUrl . '/admin/exam_papers/'],
    ], 'badge' => ($pendingApprovals + ($pendingExamPapers ?? 0)) ?: null],
    ['label' => 'Schedules',   'icon' => 'schedule', 'children' => [
        ['label' => 'Class Timetables', 'href' => $baseUrl . '/admin/schedules/'],
        ['label' => 'Approvals',        'href' => $baseUrl . '/admin/schedules/approvals.php'],
    ], 'badge' => $pendingSchedules ?: null],
    ['label' => 'Materials',   'href' => $baseUrl . '/admin/materials/',  'icon' => 'materials'],
    ['label' => 'Departments',      'href' => $baseUrl . '/admin/departments/','icon' => 'departments'],
    ['label' => 'Kankoor Waitlist', 'href' => $baseUrl . '/admin/kankoor/',     'icon' => 'kankoor'],
    ['label' => 'Drive',           'href' => $baseUrl . '/admin/drive/',        'icon' => 'drive'],
    ['label' => 'Projects',        'href' => $baseUrl . '/admin/projects/',     'icon' => 'projects',
     'badge' => (function() use ($pdo) {
         try { return (int)$pdo->query('SELECT COUNT(*) FROM student_projects WHERE status="submitted"')->fetchColumn() ?: null; }
         catch (Exception $e) { return null; }
     })()],
];

$teacherNav = [
    ['label' => 'Dashboard', 'href' => $baseUrl . '/teacher/', 'icon' => 'dashboard'],
    ['label' => 'Courses',   'icon' => 'courses', 'children' => [
        ['label' => 'My Courses',     'href' => $baseUrl . '/teacher/courses.php'],
        ['label' => 'Exam Result',    'href' => $baseUrl . '/teacher/exam_result.php'],
        ['label' => 'Exam Papers',    'href' => $baseUrl . '/teacher/exam_papers.php'],
        ['label' => 'My Students',    'href' => $baseUrl . '/teacher/students.php'],
        ['label' => 'Upload Material','href' => $baseUrl . '/teacher/upload.php'],
    ]],
    ['label' => 'Schedule',   'href' => $baseUrl . '/teacher/schedule.php', 'icon' => 'schedule'],
    ['label' => 'Attendance', 'href' => '#', 'icon' => 'attendance'],
    ['label' => 'My Profile', 'href' => $baseUrl . '/teacher/profile.php', 'icon' => 'password'],
];

$studentNav = [
    ['label' => 'Dashboard', 'href' => $baseUrl . '/student/', 'icon' => 'dashboard'],
    ['label' => 'Subjects',  'href' => $baseUrl . '/student/subjects.php', 'icon' => 'subjects'],
    ['label' => 'My Results','href' => $baseUrl . '/student/scores.php',   'icon' => 'exam_scores'],
    ['label' => 'Schedule',  'href' => $baseUrl . '/student/schedule.php',  'icon' => 'schedule'],
    ['label' => 'Materials', 'href' => $baseUrl . '/student/materials.php', 'icon' => 'materials',
     'badge' => $newMaterialsCount ?: null],
    ['label' => 'Projects',  'href' => $baseUrl . '/student/projects.php', 'icon' => 'projects'],
    ['label' => 'Attendance',      'href' => '#', 'icon' => 'attendance'],
    ['label' => 'Change Password', 'href' => $baseUrl . '/student/change_password.php', 'icon' => 'password'],
];

$nav = match($role) {
    'admin'   => $adminNav,
    'teacher' => $teacherNav,
    'student' => $studentNav,
    default   => [],
};

function sidebar_icon(string $key): string {
    return match($key) {
        'dashboard'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>',
        'teachers'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'students'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'classes'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
        'courses'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
        'subjects'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'materials'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
        'attendance'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        'exam_scores' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
        'results'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'schedule'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        'departments' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        'kankoor'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>',
        'drive'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>',
        'projects'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'password'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
        default       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>',
    };
}
?>

<aside id="sidebar"
       class="fixed left-0 top-14 bottom-0 w-56 z-40 flex flex-col overflow-y-auto fluent-scroll"
       style="background: var(--surface); border-right: 1px solid var(--border); box-shadow: var(--shadow-md);">

    <nav class="flex-1 py-2">
        <?php foreach ($nav as $item): ?>
            <?php if (!empty($item['children'])): ?>
                <div class="sidebar-group">
                    <button class="fluent-nav-item sidebar-group-btn"
                            style="justify-content: space-between; color: var(--text-secondary);">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                 style="color: var(--text-tertiary);">
                                <?= sidebar_icon($item['icon']) ?>
                            </svg>
                            <span style="font-size:13px;"><?= htmlspecialchars($item['label']) ?></span>
                        </span>
                        <span class="flex items-center gap-2">
                            <?php if (!empty($item['badge'])): ?>
                            <span style="background:#c42b1c;color:white;font-size:10px;font-weight:700;
                                         min-width:18px;height:18px;border-radius:9px;display:flex;
                                         align-items:center;justify-content:center;padding:0 5px;">
                                <?= (int)$item['badge'] ?>
                            </span>
                            <?php endif; ?>
                            <svg class="chevron w-3.5 h-3.5 transition-transform duration-200 flex-shrink-0"
                                 style="color: var(--text-tertiary);"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </button>
                    <div class="sidebar-submenu" style="background: color-mix(in srgb, var(--accent) 2%, var(--bg));">
                        <?php foreach ($item['children'] as $child): ?>
                        <a href="<?= $child['href'] ?>"
                           class="fluent-nav-item"
                           style="padding-left: 44px; font-size:13px; color: var(--text-secondary); gap: 10px;">
                            <span style="width:5px;height:5px;border-radius:50%;background:var(--text-tertiary);flex-shrink:0;display:inline-block;"></span>
                            <?= htmlspecialchars($child['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= $item['href'] ?>"
                   class="fluent-nav-item"
                   style="color: var(--text-secondary);">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color: var(--text-tertiary);">
                        <?= sidebar_icon($item['icon']) ?>
                    </svg>
                    <span style="font-size:13px;flex:1;"><?= htmlspecialchars($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                    <span style="background:#c42b1c;color:white;font-size:10px;font-weight:700;
                                 min-width:18px;height:18px;border-radius:9px;display:flex;
                                 align-items:center;justify-content:center;padding:0 5px;flex-shrink:0;">
                        <?= (int)$item['badge'] ?>
                    </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Role label at bottom -->
    <div class="px-4 py-3" style="border-top: 1px solid var(--border);">
        <p class="fluent-label capitalize" style="font-size:10px;"><?= $role ?> panel</p>
    </div>
</aside>
