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

$materialCount = $courseCount = $studentCount = 0;
if ($teacherId) {
    $materialCount = $pdo->prepare('SELECT COUNT(*) FROM materials WHERE teacher_id = ?')->execute([$teacherId]) ? $pdo->prepare('SELECT COUNT(*) FROM materials WHERE teacher_id = ?')->execute([$teacherId]) : 0;
    $s = $pdo->prepare('SELECT COUNT(*) FROM materials WHERE teacher_id = ?');
    $s->execute([$teacherId]);
    $materialCount = $s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM teacher_courses WHERE teacher_id = ?');
    $s->execute([$teacherId]);
    $courseCount = $s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM students s JOIN classes c ON s.class_id = c.id WHERE c.teacher_id = ?');
    $s->execute([$teacherId]);
    $studentCount = $s->fetchColumn();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-6 fluent-fade-in">
        <h1 class="fluent-h1">Dashboard</h1>
        <p class="fluent-caption mt-1">Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>.</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6 fluent-stagger">
        <?php foreach ([
            ['My Courses',  $courseCount,   '#0f6cbd'],
            ['My Students', $studentCount,  '#0e7a0e'],
            ['Materials',   $materialCount, '#7a3db3'],
        ] as [$label, $val, $color]): ?>
        <div class="fluent-card flex items-center gap-4 p-5">
            <div class="stat-card-bar" style="background:<?= $color ?>;"></div>
            <div>
                <p class="fluent-label"><?= $label ?></p>
                <p style="font-size:32px;font-weight:700;color:<?= $color ?>;line-height:1.1;"><?= $val ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick nav -->
    <div class="fluent-card p-0 overflow-hidden fluent-fade-in" style="animation-delay:80ms;">
        <div class="flex items-center gap-3 px-6 py-4" style="border-bottom:1px solid var(--border);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <h2 class="fluent-h3">Quick Navigation</h2>
        </div>
        <div class="p-5 grid grid-cols-3 gap-3 fluent-stagger">
            <?php foreach ([
                ['My Courses',  BASE_URL.'/teacher/courses.php',  '#0f6cbd', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ['My Students', BASE_URL.'/teacher/students.php', '#0e7a0e', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['Upload',      BASE_URL.'/teacher/upload.php',   '#7a3db3', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
            ] as [$title, $href, $color, $path]): ?>
            <a href="<?= $href ?>"
               class="quick-card fluent-card fluent-card-hover flex flex-col items-center justify-center gap-3 p-5"
               style="text-decoration:none; border-top: 3px solid <?= $color ?>;">
                <div style="width:40px;height:40px;border-radius:10px;background:color-mix(in srgb,<?= $color ?> 12%,transparent);display:flex;align-items:center;justify-content:center;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:<?= $color ?>;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/>
                    </svg>
                </div>
                <p style="font-size:13px;font-weight:600;color:var(--text);"><?= $title ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
