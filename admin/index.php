<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Dashboard — ' . SITE_NAME;

$totalTeachers  = $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
$totalStudents  = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalClasses   = $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$totalMaterials = $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Page header -->
    <div class="mb-6 fluent-fade-in">
        <h1 class="fluent-h1">Dashboard</h1>
        <p class="fluent-caption mt-1">Welcome back — here's an overview of your institute.</p>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 fluent-stagger">
        <?php
        $stats = [
            ['Teachers',  $totalTeachers,  '#0f6cbd', BASE_URL . '/admin/teachers/'],
            ['Students',  $totalStudents,  '#0e7a0e', BASE_URL . '/admin/students/'],
            ['Classes',   $totalClasses,   '#7a3db3', '#'],
            ['Materials', $totalMaterials, '#c2500f', '#'],
        ];
        foreach ($stats as [$label, $value, $color, $href]):
        ?>
        <a href="<?= $href ?>" class="fluent-card fluent-card-hover flex items-center gap-4 p-5" style="text-decoration:none;">
            <div class="stat-card-bar" style="background:<?= $color ?>;"></div>
            <div>
                <p class="fluent-label"><?= $label ?></p>
                <p style="font-size:32px;font-weight:700;color:<?= $color ?>;line-height:1.1;"><?= $value ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Quick navigation -->
    <div class="fluent-card p-0 overflow-hidden fluent-fade-in" style="animation-delay:100ms;">
        <div class="flex items-center gap-3 px-6 py-4" style="border-bottom:1px solid var(--border);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--accent);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <h2 class="fluent-h3">Quick Navigation</h2>
        </div>
        <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-3 fluent-stagger">
            <?php
            $cards = [
                ['Teachers',    BASE_URL.'/admin/teachers/', '#0f6cbd', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'Manage'],
                ['Add Teacher', BASE_URL.'/admin/teachers/add.php', '#0e7a0e', 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', 'Register'],
                ['Students',    BASE_URL.'/admin/students/', '#7a3db3', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'View all'],
                ['Materials',   '#', '#c2500f', 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'Study files'],
            ];
            foreach ($cards as [$title, $href, $color, $path, $sub]):
            ?>
            <a href="<?= $href ?>"
               class="quick-card fluent-card fluent-card-hover flex flex-col items-center justify-center gap-3 p-5 transition"
               style="text-decoration:none; border-top: 3px solid <?= $color ?>;">
                <div style="width:40px;height:40px;border-radius:10px;background:color-mix(in srgb,<?= $color ?> 12%,transparent);display:flex;align-items:center;justify-content:center;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:<?= $color ?>;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/>
                    </svg>
                </div>
                <div class="text-center">
                    <p style="font-size:13px;font-weight:600;color:var(--text);"><?= $title ?></p>
                    <p class="fluent-caption"><?= $sub ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
