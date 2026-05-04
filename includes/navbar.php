<?php $navUser = current_user(); ?>

<nav class="fixed top-0 left-0 right-0 h-14 z-50 flex items-center px-2 gap-2"
     style="background: var(--accent); box-shadow: 0 2px 8px rgba(0,0,0,0.18);">

    <!-- Hamburger -->
    <button id="sidebarToggle"
            class="p-2 rounded-md transition flex-shrink-0"
            style="color: rgba(255,255,255,0.9);"
            onmouseover="this.style.background='rgba(255,255,255,0.12)'"
            onmouseout="this.style.background='transparent'">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <!-- Logo + name -->
    <div class="flex items-center gap-2.5 flex-shrink-0 ml-1">
        <div class="w-8 h-8 flex items-center justify-center rounded-md flex-shrink-0"
             style="background: rgba(255,255,255,0.2);">
            <svg class="w-5 h-5" fill="none" stroke="white" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <span style="color: white; font-weight: 600; font-size: 15px; letter-spacing: 0.01em;">
            <?= SITE_NAME ?>
        </span>
    </div>

    <div class="flex-1"></div>

    <!-- User dropdown -->
    <div class="relative flex-shrink-0" id="userMenuWrapper">
        <button id="userMenuBtn"
                class="flex items-center gap-2 px-3 py-1.5 rounded-md transition text-sm"
                style="color: white;"
                onmouseover="this.style.background='rgba(255,255,255,0.12)'"
                onmouseout="this.style.background='transparent'">
            <!-- Avatar -->
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                 style="background: rgba(255,255,255,0.25); color: white;">
                <?= strtoupper(substr($navUser['name'], 0, 1)) ?>
            </div>
            <span style="font-size: 13px;">Hello, <?= htmlspecialchars(explode(' ', $navUser['name'])[0]) ?></span>
            <svg class="w-3.5 h-3.5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div id="userMenu"
             class="hidden absolute right-0 mt-2 w-56 fluent-card z-50 py-1 fluent-fade-in"
             style="min-width: 210px;">

            <!-- Identity -->
            <div class="px-4 py-3" style="border-bottom: 1px solid var(--border);">
                <div class="flex items-center gap-3">
                    <div class="fluent-avatar" style="width:36px;height:36px;font-size:14px;">
                        <?= strtoupper(substr($navUser['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:var(--text);">
                            <?= htmlspecialchars($navUser['name']) ?>
                        </p>
                        <p class="fluent-caption capitalize"><?= $navUser['role'] ?></p>
                    </div>
                </div>
            </div>

            <!-- Role switcher -->
            <div class="px-4 py-2.5" style="border-bottom: 1px solid var(--border);">
                <p class="fluent-label mb-2">Switch view</p>
                <div class="flex gap-1.5">
                    <?php
                    $roles = [
                        'admin'   => ['bg' => '#0f6cbd', 'label' => 'Admin'],
                        'teacher' => ['bg' => '#0e7a0e', 'label' => 'Teacher'],
                        'student' => ['bg' => '#7a3db3', 'label' => 'Student'],
                    ];
                    foreach ($roles as $r => $meta):
                        $isActive = $navUser['role'] === $r;
                    ?>
                    <a href="<?= BASE_URL ?>/auth/switch_role.php?role=<?= $r ?>"
                       style="<?= $isActive ? "background:{$meta['bg']};color:white;border-color:{$meta['bg']};" : "color:{$meta['bg']};border-color:currentColor;background:transparent;" ?> padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;border:1px solid;text-decoration:none;transition:opacity 120ms;">
                        <?= $meta['label'] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Logout -->
            <a href="<?= BASE_URL ?>/auth/logout.php"
               class="flex items-center gap-3 px-4 py-2.5 transition"
               style="color: #c42b1c; font-size:14px; text-decoration:none;"
               onmouseover="this.style.background='var(--hover)'"
               onmouseout="this.style.background='transparent'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign out
            </a>
        </div>
    </div>
</nav>
