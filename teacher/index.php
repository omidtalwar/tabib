<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'Dashboard — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT t.id FROM teachers t WHERE t.user_id = ?');
$stmt->execute([$user['id']]);
$teacher   = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

$materialCount = 0;
$studentCount  = 0;
if ($teacherId) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM materials WHERE teacher_id = ?');
    $s->execute([$teacherId]);
    $materialCount = $s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM students s JOIN classes c ON s.class_id = c.id WHERE c.teacher_id = ?');
    $s->execute([$teacherId]);
    $studentCount = $s->fetchColumn();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
            <svg class="w-5 h-5 text-blue-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <h2 class="font-bold text-gray-800">Quick Navigation</h2>
        </div>
        <div class="p-6 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <a href="<?= BASE_URL ?>/teacher/students.php"
               class="quick-card flex flex-col items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">My Students</p>
                    <p class="text-green-100 text-xs">View All</p>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/teacher/upload.php"
               class="quick-card flex flex-col items-center justify-center bg-purple-500 hover:bg-purple-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Upload</p>
                    <p class="text-purple-100 text-xs">Materials</p>
                </div>
            </a>
            <a href="#"
               class="quick-card flex flex-col items-center justify-center bg-orange-500 hover:bg-orange-600 text-white rounded-xl p-5 gap-3 transition shadow-sm hover:shadow-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <div class="text-center">
                    <p class="font-bold text-sm">Attendance</p>
                    <p class="text-orange-100 text-xs">Mark</p>
                </div>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Materials Uploaded</p>
            <p class="text-3xl font-extrabold text-purple-600"><?= $materialCount ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">My Students</p>
            <p class="text-3xl font-extrabold text-green-600"><?= $studentCount ?></p>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
