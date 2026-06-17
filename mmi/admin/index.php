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

    <!-- ── Live Activity Terminal ──────────────────────────────────────── -->
    <style>
    .act-pill {
        padding: 2px 10px; border-radius: 999px; font-size: 10px; font-weight: 600;
        border: 1px solid #30363d; background: transparent; color: #6e7681;
        cursor: pointer; transition: all .15s; font-family: inherit; letter-spacing: .02em;
    }
    .act-pill:hover { background: #21262d; color: #8b949e; }
    .act-pill-active { background: #21262d !important; color: #e6edf3 !important; border-color: #8b949e !important; }
    #activityTerminal::-webkit-scrollbar { width: 5px; }
    #activityTerminal::-webkit-scrollbar-track { background: #0d1117; }
    #activityTerminal::-webkit-scrollbar-thumb { background: #30363d; border-radius: 3px; }
    </style>

    <div class="mt-6 fluent-fade-in" style="animation-delay:200ms; border-radius:10px;
         overflow:hidden; border:1px solid #30363d; box-shadow:0 4px 24px rgba(0,0,0,.4);">

        <!-- Title bar -->
        <div style="background:#161b22; border-bottom:1px solid #30363d; padding:10px 14px;
                    display:flex; align-items:center; gap:10px;">
            <!-- macOS traffic lights -->
            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                <span style="width:12px;height:12px;border-radius:50%;background:#ff5f57;"></span>
                <span style="width:12px;height:12px;border-radius:50%;background:#febc2e;"></span>
                <span style="width:12px;height:12px;border-radius:50%;background:#28c840;"></span>
            </div>
            <span style="font-size:11px;color:#6e7681;font-family:'Cascadia Code','Fira Code',monospace;flex:1;text-align:center;">
                activity_log — system monitor
            </span>
            <!-- LIVE indicator -->
            <div style="display:flex;align-items:center;gap:5px;flex-shrink:0;">
                <span id="liveDot" style="width:7px;height:7px;border-radius:50%;background:#28c840;display:inline-block;transition:opacity .3s;"></span>
                <span style="font-size:10px;color:#28c840;font-weight:700;letter-spacing:.06em;">LIVE</span>
            </div>
            <!-- Filter pills -->
            <div style="display:flex;gap:4px;margin-left:10px;flex-shrink:0;">
                <button class="act-pill act-pill-active" data-role="all">All</button>
                <button class="act-pill" data-role="admin">Admin</button>
                <button class="act-pill" data-role="teacher">Teacher</button>
                <button class="act-pill" data-role="student">Student</button>
            </div>
        </div>

        <!-- Terminal body -->
        <div id="activityTerminal"
             style="background:#0d1117; height:300px; overflow-y:auto; padding:10px 14px;
                    font-family:'Cascadia Code','Fira Code','Consolas',monospace; font-size:12px; line-height:1.9;">
            <div id="termLines">
                <span style="color:#484f58;">Connecting to activity feed…</span>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var latestId     = 0;
        var activeFilter = 'all';
        var roleColors   = { admin:'#c084fc', teacher:'#60a5fa', student:'#4ade80', system:'#9ca3af' };
        var terminal     = document.getElementById('activityTerminal');
        var termLines    = document.getElementById('termLines');
        var baseUrl      = '<?= BASE_URL ?>';

        function esc(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function buildLine(e) {
            var c    = roleColors[e.role] || roleColors.system;
            var show = (activeFilter === 'all' || activeFilter === e.role) ? '' : 'display:none;';
            var desc = e.description
                ? ' <span style="color:#484f58;"># ' + esc(e.description) + '</span>'
                : '';
            return '<div class="term-entry" data-role="' + esc(e.role) + '" style="' + show + '">' +
                   '<span style="color:#484f58;">[' + esc(e.time_str) + ']</span>&nbsp;' +
                   '<span style="color:' + c + ';font-weight:700;">' + esc((e.role || '').toUpperCase()) + '</span>&nbsp;' +
                   '<span style="color:#e6edf3;">' + esc(e.user_name || 'System') + '</span>' +
                   '<span style="color:#484f58;"> › </span>' +
                   '<span style="color:#cdd9e5;">' + esc(e.action || '') + '</span>' +
                   desc + '</div>';
        }

        function appendLines(entries, scroll) {
            var html = '';
            entries.forEach(function (e) { html += buildLine(e); });
            termLines.insertAdjacentHTML('beforeend', html);
            if (scroll) terminal.scrollTop = terminal.scrollHeight;
        }

        function poll() {
            fetch(baseUrl + '/admin/activity_api.php?since_id=' + latestId)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.entries && data.entries.length) {
                        var atBottom = terminal.scrollHeight - terminal.scrollTop - terminal.clientHeight < 60;
                        appendLines(data.entries, atBottom);
                        latestId = data.latest_id;
                    }
                }).catch(function () {});
        }

        // Initial load
        fetch(baseUrl + '/admin/activity_api.php?since_id=0')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                termLines.innerHTML = '';
                if (data.entries && data.entries.length) {
                    appendLines(data.entries, true);
                    latestId = data.latest_id;
                } else {
                    termLines.innerHTML = '<span style="color:#484f58;">No activity yet — actions across all roles will appear here in real time.</span>';
                }
                setInterval(poll, 5000);
            })
            .catch(function () {
                termLines.innerHTML = '<span style="color:#f85149;">Could not connect to activity feed. Check server logs.</span>';
            });

        // Filter pills
        document.querySelectorAll('.act-pill').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activeFilter = btn.dataset.role;
                document.querySelectorAll('.act-pill').forEach(function (b) {
                    b.classList.toggle('act-pill-active', b.dataset.role === activeFilter);
                });
                document.querySelectorAll('.term-entry').forEach(function (el) {
                    el.style.display = (activeFilter === 'all' || el.dataset.role === activeFilter) ? '' : 'none';
                });
            });
        });

        // Pulsing LIVE dot
        var dot = document.getElementById('liveDot');
        setInterval(function () {
            dot.style.opacity = (dot.style.opacity === '0.3') ? '1' : '0.3';
        }, 900);
    });
    </script>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
