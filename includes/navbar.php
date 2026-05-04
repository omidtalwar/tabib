<?php $navUser = current_user(); ?>
<nav class="fixed top-0 left-0 right-0 h-14 bg-blue-900 text-white flex items-center z-50 shadow-md">
    <!-- Hamburger -->
    <button id="sidebarToggle"
            class="ml-3 p-2 rounded hover:bg-blue-800 transition flex-shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <!-- Institute Name (left, next to hamburger) -->
    <div class="flex items-center gap-2 ml-3 flex-shrink-0">
        <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <span class="font-bold text-base tracking-wide"><?= SITE_NAME ?></span>
    </div>

    <!-- Spacer -->
    <div class="flex-1"></div>

    <!-- User dropdown -->
    <div class="relative mr-4 flex-shrink-0" id="userMenuWrapper">
        <button id="userMenuBtn"
                class="flex items-center gap-2 px-3 py-1.5 rounded hover:bg-blue-800 transition text-sm">
            <div class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold">
                <?= strtoupper(substr($navUser['name'], 0, 1)) ?>
            </div>
            <span>Hello <?= htmlspecialchars(explode(' ', $navUser['name'])[0]) ?></span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div id="userMenu"
             class="hidden absolute right-0 mt-1 w-52 bg-white text-gray-700 rounded-lg shadow-lg py-1 text-sm">
            <div class="px-4 py-2 border-b text-xs text-gray-400 capitalize">
                Viewing as: <span class="font-semibold text-blue-700"><?= $navUser['role'] ?></span>
            </div>
            <!-- Dev role switcher -->
            <div class="px-4 py-2 border-b">
                <p class="text-xs text-gray-400 mb-1.5">Switch view</p>
                <div class="flex gap-1.5 flex-wrap">
                    <?php
                    $roleStyles = [
                        'admin'   => ['active' => 'bg-blue-700 text-white border-blue-700',    'idle' => 'text-blue-700 border-blue-300 hover:bg-blue-50'],
                        'teacher' => ['active' => 'bg-emerald-600 text-white border-emerald-600', 'idle' => 'text-emerald-700 border-emerald-300 hover:bg-emerald-50'],
                        'student' => ['active' => 'bg-purple-700 text-white border-purple-700', 'idle' => 'text-purple-700 border-purple-300 hover:bg-purple-50'],
                    ];
                    foreach ($roleStyles as $r => $styles):
                        $cls = $navUser['role'] === $r ? $styles['active'] : $styles['idle'];
                    ?>
                    <a href="<?= BASE_URL ?>/auth/switch_role.php?role=<?= $r ?>"
                       class="px-2 py-0.5 rounded text-xs font-semibold border transition <?= $cls ?>">
                        <?= ucfirst($r) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php"
               class="flex items-center gap-2 px-4 py-2 hover:bg-gray-100 text-red-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Logout
            </a>
        </div>
    </div>
</nav>
