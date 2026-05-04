<?php
$role    = $_SESSION['role'] ?? '';
$baseUrl = BASE_URL;

$adminNav = [
    ['label' => 'Home',     'href' => $baseUrl . '/admin/',          'icon' => 'home'],
    ['label' => 'Dashboard','href' => $baseUrl . '/admin/',          'icon' => 'dashboard'],
    ['label' => 'Teachers', 'icon' => 'teachers', 'children' => [
        ['label' => 'All Teachers', 'href' => $baseUrl . '/admin/teachers/'],
        ['label' => 'Add Teacher',  'href' => $baseUrl . '/admin/teachers/add.php'],
    ]],
    ['label' => 'Students', 'icon' => 'students', 'children' => [
        ['label' => 'All Students', 'href' => $baseUrl . '/admin/students/'],
        ['label' => 'Add Student',  'href' => $baseUrl . '/admin/students/add.php'],
    ]],
    ['label' => 'Classes',  'icon' => 'classes', 'children' => [
        ['label' => 'All Classes',  'href' => '#'],
        ['label' => 'Add Class',    'href' => '#'],
    ]],
    ['label' => 'Materials','href' => '#', 'icon' => 'materials'],
];

$teacherNav = [
    ['label' => 'Home',     'href' => $baseUrl . '/teacher/',         'icon' => 'home'],
    ['label' => 'Dashboard','href' => $baseUrl . '/teacher/',         'icon' => 'dashboard'],
    ['label' => 'Courses',  'icon' => 'courses', 'children' => [
        ['label' => 'My Courses',     'href' => $baseUrl . '/teacher/courses.php'],
        ['label' => 'My Students',    'href' => $baseUrl . '/teacher/students.php'],
        ['label' => 'Upload Material','href' => $baseUrl . '/teacher/upload.php'],
    ]],
    ['label' => 'Attendance','href' => '#', 'icon' => 'attendance'],
];

$studentNav = [
    ['label' => 'Home',     'href' => $baseUrl . '/student/', 'icon' => 'home'],
    ['label' => 'Dashboard','href' => $baseUrl . '/student/', 'icon' => 'dashboard'],
    ['label' => 'Courses',  'icon' => 'courses', 'children' => [
        ['label' => 'My Courses',  'href' => '#'],
        ['label' => 'My Schedule', 'href' => '#'],
        ['label' => 'Materials',   'href' => '#'],
    ]],
    ['label' => 'Attendance','href' => '#', 'icon' => 'attendance'],
];

$nav = match($role) {
    'admin'   => $adminNav,
    'teacher' => $teacherNav,
    'student' => $studentNav,
    default   => [],
};

function sidebar_icon(string $key): string {
    return match($key) {
        'home'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
        'dashboard'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>',
        'teachers'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'students'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'classes'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
        'courses'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
        'materials'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
        'attendance' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        default      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>',
    };
}
?>

<aside id="sidebar"
       class="fixed left-0 top-14 bottom-0 w-56 bg-white shadow-lg z-40 flex flex-col overflow-y-auto transition-transform duration-300">

    <nav class="flex-1 py-3">
        <?php foreach ($nav as $item): ?>
            <?php if (!empty($item['children'])): ?>
                <!-- Accordion item -->
                <div class="sidebar-group">
                    <button class="sidebar-group-btn w-full flex items-center justify-between px-5 py-2.5 text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition text-sm font-medium">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?= sidebar_icon($item['icon']) ?>
                            </svg>
                            <?= htmlspecialchars($item['label']) ?>
                        </span>
                        <svg class="chevron w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="sidebar-submenu hidden bg-gray-50">
                        <?php foreach ($item['children'] as $child): ?>
                        <a href="<?= $child['href'] ?>"
                           class="flex items-center gap-3 pl-12 pr-5 py-2 text-sm text-gray-600 hover:text-blue-700 hover:bg-blue-50 transition">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                            <?= htmlspecialchars($child['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Plain link -->
                <a href="<?= $item['href'] ?>"
                   class="flex items-center gap-3 px-5 py-2.5 text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition text-sm font-medium">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?= sidebar_icon($item['icon']) ?>
                    </svg>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
