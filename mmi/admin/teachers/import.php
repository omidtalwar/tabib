<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Import Teachers — ' . SITE_NAME;

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = get_departments($pdo);

/* ── Next teacher_no ────────────────────────────────────────── */
$maxNo = (int)$pdo->query(
    "SELECT MAX(CAST(SUBSTRING_INDEX(teacher_no,'-',-1) AS UNSIGNED)) FROM teachers"
)->fetchColumn();
$nextNo = $maxNo + 1;

/* ── Handle bulk import ─────────────────────────────────────── */
$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_import') {
    $defaultDept = trim($_POST['department'] ?? '');
    $password    = trim($_POST['password']   ?? 'mmi1234');
    $noPfx       = strtoupper(trim($_POST['no_prefix']  ?? 'TCH'));
    $noStart     = max(1, (int)($_POST['no_start'] ?? 1));
    $rawJson     = trim($_POST['teachers_json'] ?? '');

    $teachers = json_decode($rawJson, true);
    if (!is_array($teachers) || empty($teachers)) {
        $importResult = ['error' => 'No teacher data received.'];
    } else {
        $inserted = 0;
        $skipped  = 0;
        $errors   = [];

        try {
            $pdo->beginTransaction();
            $noIdx = $noStart;

            foreach ($teachers as $t) {
                $name  = trim($t['name']          ?? '');
                $qual  = trim($t['qualification'] ?? '') ?: null;
                $dept  = trim($t['department']    ?? '') ?: ($defaultDept ?: null);
                if (!$name) { $skipped++; continue; }

                $teacherNo = $noPfx . '-' . str_pad($noIdx, 4, '0', STR_PAD_LEFT);
                $email     = 'teacher_' . uniqid('', true) . '@mmi.local';

                try {
                    $pdo->prepare(
                        'INSERT INTO users (name, email, password, role) VALUES (?,?,?,"teacher")'
                    )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $uid = $pdo->lastInsertId();

                    $pdo->prepare(
                        'INSERT INTO teachers (user_id, teacher_no, qualification, department) VALUES (?,?,?,?)'
                    )->execute([$uid, $teacherNo, $qual, $dept]);

                    $inserted++;
                    $noIdx++;
                } catch (PDOException $e) {
                    $skipped++;
                    $errors[] = $name . ': ' . (str_contains($e->getMessage(), 'Duplicate')
                        ? 'duplicate' : $e->getMessage());
                }
            }
            $pdo->commit();

            log_activity($pdo, 'teachers_bulk_imported', "$inserted teachers imported");
            $importResult = ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
        } catch (Exception $e) {
            $pdo->rollBack();
            $importResult = ['error' => $e->getMessage()];
        }
    }
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Import Teachers from Excel</h1>
            <p class="fluent-caption mt-1">
                Excel format: Column A = Name, Column B = Qualification (optional), Column C = Department (optional).
                Data starts from row 2.
            </p>
        </div>
        <a href="index.php" class="fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    <?php if ($importResult): ?>
        <?php if (isset($importResult['error'])): ?>
        <div class="fluent-alert fluent-alert-danger mb-5"><?= htmlspecialchars($importResult['error']) ?></div>
        <?php else: ?>
        <div class="fluent-alert fluent-alert-success mb-5">
            <strong><?= $importResult['inserted'] ?></strong> teachers imported successfully.
            <?php if ($importResult['skipped']): ?>
            <span style="color:var(--text-secondary);margin-left:8px;">(<?= $importResult['skipped'] ?> skipped)</span>
            <?php endif; ?>
            <a href="index.php" style="color:var(--accent);margin-left:12px;">View all teachers →</a>
        </div>
        <?php if (!empty($importResult['errors'])): ?>
        <div class="fluent-card p-4 mb-5" style="border-color:color-mix(in srgb,#c42b1c 25%,transparent);">
            <p class="fluent-label mb-2" style="color:#c42b1c;">Errors:</p>
            <?php foreach ($importResult['errors'] as $e): ?>
            <p style="font-size:12px;color:var(--text-secondary);">• <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 fluent-fade-in" style="animation-delay:40ms;">

        <!-- Left: Upload + Settings -->
        <div class="lg:col-span-1 space-y-4">

            <!-- File upload -->
            <div class="fluent-card p-5">
                <h2 class="fluent-h2 mb-4">1. Upload Excel File</h2>

                <div id="dropZone"
                     style="border:2px dashed var(--border);border-radius:8px;padding:28px 16px;
                            text-align:center;cursor:pointer;transition:all .2s;"
                     onclick="document.getElementById('xlsxFile').click()">
                    <input type="file" id="xlsxFile" accept=".xlsx" style="display:none;">
                    <svg id="dropIcon" class="w-9 h-9 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p id="dropLabel" style="font-size:13px;color:var(--text-secondary);">
                        Drag &amp; drop or <span style="color:var(--accent);">browse</span>
                    </p>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">.xlsx files only</p>
                </div>

                <button id="parseBtn" disabled class="fluent-btn-accent fluent-btn w-full mt-3" style="opacity:.4;">
                    Parse Teachers
                </button>
                <div id="parseStatus" style="font-size:12px;color:var(--text-tertiary);text-align:center;margin-top:6px;min-height:16px;"></div>
            </div>

            <!-- Settings -->
            <div class="fluent-card p-5">
                <h2 class="fluent-h2 mb-4">2. Import Settings</h2>
                <div class="space-y-4">

                    <div>
                        <label class="fluent-label block mb-1.5">Default Department</label>
                        <div class="fluent-input">
                            <select id="deptSel" name="department">
                                <option value="">— Use Excel column —</option>
                                <?php foreach ($allDepts as $d): ?>
                                <option value="<?= htmlspecialchars($d['name_en']) ?>">
                                    <?= htmlspecialchars($d['name_en']) ?>
                                    <?php if ($d['name_ps']): ?>(<?= htmlspecialchars($d['name_ps']) ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                            Overrides department from the Excel file if set.
                        </p>
                    </div>

                    <hr class="fluent-divider">

                    <div>
                        <label class="fluent-label block mb-1.5">Default Password *</label>
                        <div class="fluent-input">
                            <input type="text" id="passInput" value="mmi1234" placeholder="e.g. mmi1234">
                        </div>
                    </div>

                    <div>
                        <label class="fluent-label block mb-1.5">Teacher ID — Prefix &amp; Start</label>
                        <div class="flex gap-2">
                            <div class="fluent-input" style="flex:0 0 80px;">
                                <input type="text" id="noPfx" value="TCH" maxlength="6" style="text-transform:uppercase;">
                            </div>
                            <div class="fluent-input flex-1">
                                <input type="number" id="noStart" value="<?= $nextNo ?>" min="1">
                            </div>
                        </div>
                        <p id="noPreview" style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                            First ID: TCH-<?= str_pad($nextNo, 4, '0', STR_PAD_LEFT) ?>
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right: Preview table -->
        <div class="lg:col-span-2">
            <div class="fluent-card overflow-hidden" id="previewCard" style="min-height:280px;display:flex;flex-direction:column;">

                <div class="flex items-center justify-between p-4" style="border-bottom:1px solid var(--border);">
                    <div>
                        <h2 class="fluent-h2">3. Preview &amp; Confirm</h2>
                        <p class="fluent-caption" id="previewCaption">Parse an Excel file to see teachers here.</p>
                    </div>
                    <button id="importBtn" disabled class="fluent-btn-accent fluent-btn" style="opacity:.4;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Import All
                    </button>
                </div>

                <div id="emptyState" class="flex-1 flex flex-col items-center justify-center p-12"
                     style="color:var(--text-tertiary);">
                    <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p style="font-size:14px;">Upload an Excel file and click Parse Teachers</p>
                </div>

                <div id="tableWrap" class="flex-1 overflow-auto" style="display:none;">
                    <table class="fluent-table w-full">
                        <thead>
                            <tr>
                                <th style="width:48px;text-align:center;">#</th>
                                <th style="width:90px;">ID</th>
                                <th>Name</th>
                                <th>Qualification</th>
                                <th>Department</th>
                                <th style="width:36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden import form -->
    <form method="POST" id="importForm" style="display:none;">
        <input type="hidden" name="action"        value="bulk_import">
        <input type="hidden" name="department"    id="fDept">
        <input type="hidden" name="password"      id="fPass">
        <input type="hidden" name="no_prefix"     id="fNoPfx">
        <input type="hidden" name="no_start"      id="fNoStart">
        <input type="hidden" name="teachers_json" id="fJson">
    </form>
</main>

<script>
(function () {
    var BASE = '<?= BASE_URL ?>';
    var rows = [];

    var dropZone    = document.getElementById('dropZone');
    var fileInput   = document.getElementById('xlsxFile');
    var parseBtn    = document.getElementById('parseBtn');
    var parseStatus = document.getElementById('parseStatus');
    var importBtn   = document.getElementById('importBtn');
    var previewBody = document.getElementById('previewBody');
    var tableWrap   = document.getElementById('tableWrap');
    var emptyState  = document.getElementById('emptyState');
    var previewCap  = document.getElementById('previewCaption');
    var noPfx       = document.getElementById('noPfx');
    var noStart     = document.getElementById('noStart');
    var noPreview   = document.getElementById('noPreview');

    function pad(n, len) { return String(n).padStart(len, '0'); }
    function idFor(i) {
        return (noPfx.value || 'TCH').toUpperCase() + '-' + pad(parseInt(noStart.value||'1') + i, 4);
    }
    function refreshIds() {
        noPreview.textContent = 'First ID: ' + idFor(0);
        document.querySelectorAll('.id-cell').forEach(function(td, i) { td.textContent = idFor(i); });
    }
    noPfx.addEventListener('input', refreshIds);
    noStart.addEventListener('input', refreshIds);

    dropZone.addEventListener('dragover',  function(e){ e.preventDefault(); this.style.borderColor='var(--accent)'; });
    dropZone.addEventListener('dragleave', function(){ this.style.borderColor='var(--border)'; });
    dropZone.addEventListener('drop', function(e){
        e.preventDefault(); this.style.borderColor='var(--border)';
        var f = e.dataTransfer.files[0];
        if (f) { fileInput.files = e.dataTransfer.files; onFileSelected(f); }
    });
    fileInput.addEventListener('change', function(){ if (this.files[0]) onFileSelected(this.files[0]); });

    function onFileSelected(f) {
        document.getElementById('dropLabel').innerHTML = '<strong style="color:var(--accent);">' + f.name + '</strong>';
        parseBtn.disabled = false; parseBtn.style.opacity = '1';
        parseStatus.textContent = '';
    }

    parseBtn.addEventListener('click', function() {
        if (!fileInput.files[0]) return;
        parseBtn.disabled = true;
        parseStatus.textContent = 'Parsing…';

        var fd = new FormData();
        fd.append('xlsx', fileInput.files[0]);

        fetch(BASE + '/admin/teachers/parse_excel.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
                parseBtn.disabled = false;
                if (data.error) { parseStatus.textContent = data.error; parseStatus.style.color='#c42b1c'; return; }
                rows = data.teachers || [];
                parseStatus.textContent = data.count + ' teachers found.';
                parseStatus.style.color = 'var(--text-tertiary)';
                renderTable();
            })
            .catch(function(e){ parseBtn.disabled=false; parseStatus.textContent='Error: '+e.message; parseStatus.style.color='#c42b1c'; });
    });

    function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

    function renderTable() {
        previewBody.innerHTML = '';
        if (!rows.length) {
            emptyState.style.display = ''; tableWrap.style.display = 'none';
            previewCap.textContent = 'No teachers found.';
            importBtn.disabled = true; importBtn.style.opacity = '.4'; return;
        }
        emptyState.style.display = 'none'; tableWrap.style.display = '';
        previewCap.textContent = rows.length + ' teachers — review and confirm.';
        importBtn.disabled = false; importBtn.style.opacity = '1';

        rows.forEach(function(t, i) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="text-align:center;font-size:12px;color:var(--text-tertiary);">' + (i+1) + '</td>' +
                '<td class="id-cell" style="font-family:monospace;font-size:12px;color:var(--accent);">' + idFor(i) + '</td>' +
                '<td><input data-field="name" data-i="'+i+'" value="'+esc(t.name)+'" style="border:none;background:transparent;width:100%;font-size:13px;outline:none;font-weight:600;"></td>' +
                '<td><input data-field="qualification" data-i="'+i+'" value="'+esc(t.qualification)+'" style="border:none;background:transparent;width:100%;font-size:12px;outline:none;color:var(--text-secondary);"></td>' +
                '<td><input data-field="department" data-i="'+i+'" value="'+esc(t.department)+'" style="border:none;background:transparent;width:100%;font-size:12px;outline:none;color:var(--text-secondary);"></td>' +
                '<td><button type="button" data-i="'+i+'" class="del-row" style="color:#c42b1c;background:none;border:none;cursor:pointer;font-size:14px;padding:2px 6px;">&times;</button></td>';
            previewBody.appendChild(tr);
        });

        previewBody.addEventListener('input', function(e) {
            var inp = e.target;
            if (inp.dataset.field) rows[inp.dataset.i][inp.dataset.field] = inp.value;
        });
        previewBody.addEventListener('click', function(e) {
            var btn = e.target.closest('.del-row');
            if (btn) { rows.splice(parseInt(btn.dataset.i),1); renderTable(); }
        });
    }

    importBtn.addEventListener('click', function() {
        var dept  = document.getElementById('deptSel').value;
        var pass  = document.getElementById('passInput').value;
        if (!pass) { alert('Enter a default password.'); return; }
        if (!rows.length) { alert('No teachers to import.'); return; }
        if (!confirm('Import ' + rows.length + ' teachers?')) return;

        document.getElementById('fDept').value    = dept;
        document.getElementById('fPass').value    = pass;
        document.getElementById('fNoPfx').value   = (noPfx.value||'TCH').toUpperCase();
        document.getElementById('fNoStart').value = noStart.value||'1';
        document.getElementById('fJson').value    = JSON.stringify(rows);
        document.getElementById('importForm').submit();
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
