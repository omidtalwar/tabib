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

    <!-- Notification bell (student only) -->
    <?php if (($navUser['role'] ?? '') === 'student'): ?>
    <div class="relative flex-shrink-0" id="notifWrapper">
        <button id="notifBtn"
                class="relative p-2 rounded-md transition flex-shrink-0"
                style="color: rgba(255,255,255,0.9);"
                title="Notifications"
                onmouseover="this.style.background='rgba(255,255,255,0.12)'"
                onmouseout="this.style.background='transparent'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span id="notifBadge"
                  style="display:none;position:absolute;top:4px;right:4px;background:#c42b1c;color:white;
                         font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;
                         display:none;align-items:center;justify-content:center;padding:0 3px;
                         line-height:1;border:1.5px solid var(--accent);">
                0
            </span>
        </button>

        <!-- Dropdown -->
        <div id="notifDropdown"
             style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:340px;
                    background:var(--surface);border:1px solid var(--border);border-radius:10px;
                    box-shadow:var(--shadow-lg);z-index:200;overflow:hidden;">

            <div style="padding:12px 16px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13px;font-weight:600;color:var(--text);">Notifications</span>
                <span id="notifMarkRead"
                      style="font-size:11px;color:var(--accent);cursor:pointer;font-weight:500;">Mark all read</span>
            </div>

            <div id="notifList"
                 style="max-height:360px;overflow-y:auto;">
                <div style="padding:24px 16px;text-align:center;color:var(--text-tertiary);font-size:13px;">
                    Loading…
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var STORAGE_KEY = 'notif_last_seen_<?= $navUser['id'] ?>';
        var BASE        = '<?= BASE_URL ?>';
        var badge       = document.getElementById('notifBadge');
        var list        = document.getElementById('notifList');
        var dropdown    = document.getElementById('notifDropdown');
        var btn         = document.getElementById('notifBtn');
        var markRead    = document.getElementById('notifMarkRead');
        var open        = false;
        var typeIcons   = {
            material: '<svg style="color:#60a5fa" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
            result:   '<svg style="color:#4ade80" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        };

        function getLastSeen() {
            var v = localStorage.getItem(STORAGE_KEY);
            return v ? parseInt(v, 10) : Math.floor(Date.now() / 1000) - 7 * 86400;
        }
        function setLastSeen(ts) {
            localStorage.setItem(STORAGE_KEY, ts);
        }

        function renderList(notifs) {
            if (!notifs.length) {
                list.innerHTML = '<div style="padding:32px 16px;text-align:center;color:var(--text-tertiary);font-size:13px;">No new notifications</div>';
                return;
            }
            var html = '';
            notifs.forEach(function (n) {
                html += '<a href="' + BASE + '/student/' + n.url + '" style="display:flex;align-items:flex-start;gap:10px;padding:11px 16px;text-decoration:none;border-bottom:1px solid var(--border);transition:background 120ms;" onmouseover="this.style.background=\'var(--hover)\'" onmouseout="this.style.background=\'transparent\'">'
                    + '<div style="width:32px;height:32px;border-radius:8px;background:color-mix(in srgb,var(--accent) 10%,transparent);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">'
                    + (typeIcons[n.type] || '')
                    + '</div>'
                    + '<div style="flex:1;min-width:0;">'
                    + '<p style="font-size:13px;font-weight:600;color:var(--text);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + n.title + '</p>'
                    + '<p style="font-size:11px;color:var(--text-secondary);margin:2px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + n.desc + '</p>'
                    + '</div>'
                    + '<span style="font-size:10px;color:var(--text-tertiary);flex-shrink:0;padding-top:2px;">' + n.time_ago + '</span>'
                    + '</a>';
            });
            list.innerHTML = html;
        }

        function poll() {
            var lastSeen = getLastSeen();
            fetch(BASE + '/student/notifications_api.php?since_ts=' + lastSeen)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = (data.notifications || []).length;
                    if (count > 0) {
                        badge.textContent = count > 9 ? '9+' : count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                    if (open) renderList(data.notifications || []);
                })
                .catch(function () {});
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            open = !open;
            dropdown.style.display = open ? 'block' : 'none';
            if (open) {
                // Load fresh list
                var lastSeen = getLastSeen();
                fetch(BASE + '/student/notifications_api.php?since_ts=' + lastSeen)
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderList(data.notifications || []); })
                    .catch(function () { list.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-tertiary);font-size:13px;">Could not load.</div>'; });
            }
        });

        markRead.addEventListener('click', function (e) {
            e.stopPropagation();
            setLastSeen(Math.floor(Date.now() / 1000));
            badge.style.display = 'none';
            renderList([]);
        });

        document.addEventListener('click', function (e) {
            if (!document.getElementById('notifWrapper').contains(e.target)) {
                open = false;
                dropdown.style.display = 'none';
            }
        });

        // Close dropdown on link click inside
        list.addEventListener('click', function () {
            setLastSeen(Math.floor(Date.now() / 1000));
        });

        poll();
        setInterval(poll, 30000);
    })();
    </script>
    <?php endif; ?>

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
