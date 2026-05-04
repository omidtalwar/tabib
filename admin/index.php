<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Dashboard — ' . SITE_NAME;

$totalTeachers = $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
$totalStudents = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalClasses  = $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$totalMaterials = $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Quick Navigation -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
            <svg class="w-5 h-5 text-blue-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <h2 class="font-bold text-gray-800">Quick Navigation</h2>
        </div>
        <div class="p-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">

            <!-- Teachers -->
            <a href="<?= BASE_URL ?>/admin/teachers/"
               class="quick-card flex flex-col items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Teachers</p>
                    <p class="text-green-100 text-xs">Management</p>
                </div>
            </a>

            <!-- Classes -->
            <a href="#"
               class="quick-card flex flex-col items-center justify-center bg-purple-500 hover:bg-purple-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Classes</p>
                    <p class="text-purple-100 text-xs">Schedule</p>
                </div>
            </a>

            <!-- Students -->
            <a href="<?= BASE_URL ?>/admin/students/"
               class="quick-card flex flex-col items-center justify-center bg-teal-500 hover:bg-teal-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Students</p>
                    <p class="text-teal-100 text-xs">List</p>
                </div>
            </a>

            <!-- Materials -->
            <a href="#"
               class="quick-card flex flex-col items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Materials</p>
                    <p class="text-red-100 text-xs">Study Files</p>
                </div>
            </a>

            <!-- Attendance -->
            <a href="#"
               class="quick-card flex flex-col items-center justify-center bg-orange-500 hover:bg-orange-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Attendance</p>
                    <p class="text-orange-100 text-xs">Schedule</p>
                </div>
            </a>

            <!-- Add Teacher shortcut -->
            <a href="<?= BASE_URL ?>/admin/teachers/add.php"
               class="quick-card flex flex-col items-center justify-center bg-green-700 hover:bg-green-800 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Add Teacher</p>
                    <p class="text-green-200 text-xs">Register</p>
                </div>
            </a>

            <!-- Notifications -->
            <a href="#"
               class="quick-card flex flex-col items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Send</p>
                    <p class="text-yellow-100 text-xs">Notification</p>
                </div>
            </a>

            <!-- Reports -->
            <a href="#"
               class="quick-card flex flex-col items-center justify-center bg-blue-700 hover:bg-blue-800 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Dashboard</p>
                    <p class="text-blue-200 text-xs">Reporting</p>
                </div>
            </a>

        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Teachers</p>
            <p class="text-3xl font-extrabold text-green-600"><?= $totalTeachers ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Students</p>
            <p class="text-3xl font-extrabold text-teal-600"><?= $totalStudents ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Classes</p>
            <p class="text-3xl font-extrabold text-purple-600"><?= $totalClasses ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Materials</p>
            <p class="text-3xl font-extrabold text-red-500"><?= $totalMaterials ?></p>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
