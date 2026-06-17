<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../includes/departments.php';
$allDepts = get_departments($pdo);
$shifts = ['06:00 – 09:00','09:00 – 12:00','01:00 – 04:00'];

/* ── Handle bulk import POST ───────────────────────────────── */
$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_import') {
    $dept     = trim($_POST['department'] ?? '');
    $sem      = trim($_POST['semester']   ?? '');
    $shift    = trim($_POST['shift']      ?? '');
    $password = trim($_POST['password']   ?? 'mmi1234');
    $rollPfx  = trim($_POST['roll_prefix'] ?? 'MMI');
    $rollStart = max(1, (int)($_POST['roll_start'] ?? 1));
    $rawJson  = trim($_POST['students_json'] ?? '');

    $students = json_decode($rawJson, true);
    if (!is_array($students) || empty($students)) {
        $importResult = ['error' => 'No student data received.'];
    } else {
        $inserted = 0;
        $skipped  = 0;
        $errors   = [];

        try {
            $pdo->beginTransaction();
            $rollIdx = $rollStart;

            foreach ($students as $s) {
                $name   = trim($s['name']        ?? '');
                $father = trim($s['father_name'] ?? '');
                if (!$name) { $skipped++; continue; }

                $rollNo = $rollPfx . '-' . str_pad($rollIdx, 5, '0', STR_PAD_LEFT);
                $email  = 'student_' . uniqid('', true) . '@mmi.local';

                try {
                    $pdo->prepare(
                        'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "student")'
                    )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $uid = $pdo->lastInsertId();

                    $pdo->prepare(
                        'INSERT INTO students (user_id, roll_no, father_name, department, semester, shift)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$uid, $rollNo, $father ?: null,
                                $dept ?: null, $sem ?: null, $shift ?: null]);

                    $inserted++;
                    $rollIdx++;
                } catch (PDOException $e) {
                    $skipped++;
                    $errors[] = $name . ': ' . (str_contains($e->getMessage(), 'Duplicate')
                        ? 'duplicate roll/email' : $e->getMessage());
                }
            }
            $pdo->commit();

            log_activity($pdo, 'students_bulk_imported',
                "$inserted students imported — $dept $sem $shift");

            $importResult = ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
        } catch (Exception $e) {
            $pdo->rollBack();
            $importResult = ['error' => $e->getMessage()];
        }
    }
}

/* ── Find next roll number ─────────────────────────────────── */
$maxRoll = (int)$pdo->query(
    "SELECT MAX(CAST(SUBSTRING_INDEX(roll_no,'-',-1) AS UNSIGNED)) FROM students"
)->fetchColumn();
$nextRoll = $maxRoll + 1;

$pageTitle = 'Import Students — ' . SITE_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/navbar.php'; ?>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <!-- Header -->
    <div class="flex justify-between items-center mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Import Students from Excel</h1>
            <p class="fluent-caption mt-1">Upload the class Excel sheet to bulk-register students.</p>
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
        <div class="fluent-alert fluent-alert-danger mb-5">
            <?= htmlspecialchars($importResult['error']) ?>
        </div>
        <?php else: ?>
        <div class="fluent-alert fluent-alert-success mb-5">
            <strong><?= $importResult['inserted'] ?></strong> students imported successfully.
            <?php if ($importResult['skipped']): ?>
                <span style="color:var(--text-secondary);margin-left:8px;">
                    (<?= $importResult['skipped'] ?> skipped)
                </span>
            <?php endif; ?>
            <a href="index.php" style="color:var(--accent);margin-left:12px;">View all students →</a>
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

        <!-- ── Left: Settings panel ── -->
        <div class="lg:col-span-1 space-y-4">

            <!-- File upload card -->
            <div class="fluent-card p-5">
                <h2 class="fluent-h2 mb-4">1. Upload Excel File</h2>

                <div id="dropZone"
                     style="border:2px dashed var(--border);border-radius:8px;padding:32px 16px;
                            text-align:center;cursor:pointer;transition:all .2s;position:relative;"
                     onclick="document.getElementById('xlsxFile').click()">
                    <input type="file" id="xlsxFile" accept=".xlsx" class="hidden" style="display:none;">
                    <svg id="dropIcon" class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p id="dropLabel" style="font-size:13px;color:var(--text-secondary);">
                        Drag &amp; drop or <span style="color:var(--accent);">browse</span>
                    </p>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">.xlsx files only</p>
                </div>

                <button id="parseBtn" disabled
                        class="fluent-btn-accent fluent-btn w-full mt-3"
                        style="opacity:.4;transition:opacity .2s;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Parse Students
                </button>
                <div id="parseStatus" style="font-size:12px;color:var(--text-tertiary);text-align:center;margin-top:6px;min-height:16px;"></div>
            </div>

            <!-- Import settings card -->
            <div class="fluent-card p-5">
                <h2 class="fluent-h2 mb-4">2. Class Settings</h2>
                <div class="space-y-4">

                    <!-- Department -->
                    <div>
                        <label class="fluent-label block mb-1.5">Department *</label>
                        <div class="fluent-input">
                            <select id="deptSel" name="department">
                                <option value="">— Select —</option>
                                <?php foreach ($allDepts as $d): ?>
                                <option value="<?= htmlspecialchars($d['name_en']) ?>"
                                        data-max="<?= (int)$d['max_semesters'] ?>">
                                    <?= htmlspecialchars($d['name_en']) ?>
                                    <?php if ($d['name_ps']): ?>(<?= htmlspecialchars($d['name_ps']) ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Semester -->
                    <div>
                        <label class="fluent-label block mb-1.5">Semester *</label>
                        <div class="fluent-input">
                            <select id="semSel" name="semester">
                                <option value="">— Select department first —</option>
                            </select>
                        </div>
                    </div>

                    <!-- Shift -->
                    <div>
                        <label class="fluent-label block mb-1.5">Shift *</label>
                        <div class="fluent-input">
                            <select id="shiftSel" name="shift">
                                <option value="">— Select —</option>
                                <?php foreach ($shifts as $sh): ?>
                                <option><?= $sh ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="fluent-divider">

                    <!-- Default password -->
                    <div>
                        <label class="fluent-label block mb-1.5">Default Password *</label>
                        <div class="fluent-input">
                            <input type="text" id="passInput" value="mmi1234" placeholder="e.g. mmi1234">
                        </div>
                        <p style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                            All imported students will share this password.
                        </p>
                    </div>

                    <!-- Roll number format -->
                    <div>
                        <label class="fluent-label block mb-1.5">Roll No — Prefix &amp; Start</label>
                        <div class="flex gap-2">
                            <div class="fluent-input" style="flex:0 0 90px;">
                                <input type="text" id="rollPfx" value="MMI" placeholder="MMI" maxlength="10"
                                       style="text-transform:uppercase;">
                            </div>
                            <div class="fluent-input flex-1">
                                <input type="number" id="rollStart" value="<?= $nextRoll ?>" min="1" placeholder="1">
                            </div>
                        </div>
                        <p id="rollPreview" style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">
                            First roll: MMI-<?= str_pad($nextRoll, 5, '0', STR_PAD_LEFT) ?>
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Right: Preview table ── -->
        <div class="lg:col-span-2">
            <div class="fluent-card overflow-hidden" id="previewCard"
                 style="min-height:300px;display:flex;flex-direction:column;">

                <div class="flex items-center justify-between p-4"
                     style="border-bottom:1px solid var(--border);">
                    <div>
                        <h2 class="fluent-h2">3. Preview &amp; Confirm</h2>
                        <p class="fluent-caption" id="previewCaption">
                            Parse an Excel file to see students here.
                        </p>
                    </div>
                    <button id="importBtn" disabled
                            class="fluent-btn-accent fluent-btn"
                            style="opacity:.4;transition:opacity .2s;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Import All
                    </button>
                </div>

                <!-- Empty state -->
                <div id="emptyState" class="flex-1 flex flex-col items-center justify-center p-12"
                     style="color:var(--text-tertiary);">
                    <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p style="font-size:14px;">Upload an Excel file and click Parse Students</p>
                </div>

                <!-- Table (hidden until parsed) -->
                <div id="tableWrap" class="flex-1 overflow-auto" style="display:none;">
                    <table class="fluent-table w-full">
                        <thead>
                            <tr>
                                <th style="width:48px;text-align:center;">#</th>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Father's Name</th>
                                <th style="width:36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Hidden import form -->
            <form method="POST" id="importForm" style="display:none;">
                <input type="hidden" name="action"       value="bulk_import">
                <input type="hidden" name="department"   id="fDept">
                <input type="hidden" name="semester"     id="fSem">
                <input type="hidden" name="shift"        id="fShift">
                <input type="hidden" name="password"     id="fPass">
                <input type="hidden" name="roll_prefix"  id="fRollPfx">
                <input type="hidden" name="roll_start"   id="fRollStart">
                <input type="hidden" name="students_json" id="fJson">
            </form>
        </div>
    </div>
</main>

<script>
(function () {
    var BASE  = '<?= BASE_URL ?>';
    var rows  = []; // [{name, father_name}]

    /* ── DOM refs ── */
    var dropZone    = document.getElementById('dropZone');
    var fileInput   = document.getElementById('xlsxFile');
    var parseBtn    = document.getElementById('parseBtn');
    var parseStatus = document.getElementById('parseStatus');
    var importBtn   = document.getElementById('importBtn');
    var previewBody = document.getElementById('previewBody');
    var tableWrap   = document.getElementById('tableWrap');
    var emptyState  = document.getElementById('emptyState');
    var previewCap  = document.getElementById('previewCaption');
    var deptSel     = document.getElementById('deptSel');
    var semSel      = document.getElementById('semSel');
    var rollPfx     = document.getElementById('rollPfx');
    var rollStart   = document.getElementById('rollStart');
    var rollPreview = document.getElementById('rollPreview');

    /* ── Department → semester cascade ── */
    var deptData = <?= json_encode(array_column($allDepts, null, 'name_en')) ?>;

    deptSel.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        var max = opt ? parseInt(opt.dataset.max || '4') : 4;
        semSel.innerHTML = '<option value="">— Select semester —</option>';
        var suf = ['','st','nd','rd'];
        for (var i = 1; i <= max; i++) {
            var s = i + (i <= 3 ? suf[i] : 'th') + ' Semester';
            var el = document.createElement('option');
            el.textContent = s;
            semSel.appendChild(el);
        }
        refreshRolls();
    });

    /* ── Roll preview ── */
    function pad(n, len) { return String(n).padStart(len, '0'); }

    function rollFor(i) {
        var pfx = (rollPfx.value || 'MMI').toUpperCase();
        var num = parseInt(rollStart.value || '1') + i;
        return pfx + '-' + pad(num, 5);
    }

    function refreshRolls() {
        var pfx   = (rollPfx.value || 'MMI').toUpperCase();
        var start = parseInt(rollStart.value || '1');
        rollPreview.textContent = 'First roll: ' + pfx + '-' + pad(start, 5);
        // update table cells
        document.querySelectorAll('.roll-cell').forEach(function (td, i) {
            td.textContent = rollFor(i);
        });
    }

    rollPfx.addEventListener('input', refreshRolls);
    rollStart.addEventListener('input', refreshRolls);

    /* ── Drag & drop ── */
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = 'var(--accent)';
        this.style.background  = 'color-mix(in srgb,var(--accent) 5%,transparent)';
    });
    dropZone.addEventListener('dragleave', function () {
        this.style.borderColor = 'var(--border)';
        this.style.background  = '';
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        this.style.borderColor = 'var(--border)';
        this.style.background  = '';
        var f = e.dataTransfer.files[0];
        if (f) { fileInput.files = e.dataTransfer.files; onFileSelected(f); }
    });
    fileInput.addEventListener('change', function () {
        if (this.files[0]) onFileSelected(this.files[0]);
    });

    function onFileSelected(f) {
        document.getElementById('dropLabel').innerHTML =
            '<strong style="color:var(--accent);">' + f.name + '</strong>';
        parseBtn.disabled = false;
        parseBtn.style.opacity = '1';
        parseStatus.textContent = '';
    }

    /* ── Parse ── */
    parseBtn.addEventListener('click', function () {
        if (!fileInput.files[0]) return;
        parseBtn.disabled = true;
        parseStatus.textContent = 'Parsing…';

        var fd = new FormData();
        fd.append('xlsx', fileInput.files[0]);

        fetch(BASE + '/admin/students/parse_excel.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                parseBtn.disabled = false;
                if (data.error) {
                    parseStatus.textContent = data.error;
                    parseStatus.style.color = '#c42b1c';
                    return;
                }
                rows = data.students || [];
                parseStatus.textContent = data.count + ' students found.';
                parseStatus.style.color = 'var(--text-tertiary)';
                renderTable();
            })
            .catch(function (e) {
                parseBtn.disabled = false;
                parseStatus.textContent = 'Error: ' + e.message;
                parseStatus.style.color = '#c42b1c';
            });
    });

    /* ── Render preview table ── */
    function renderTable() {
        previewBody.innerHTML = '';
        if (rows.length === 0) {
            emptyState.style.display = '';
            tableWrap.style.display  = 'none';
            previewCap.textContent   = 'No students found in file.';
            importBtn.disabled = true;
            importBtn.style.opacity = '.4';
            return;
        }

        emptyState.style.display = 'none';
        tableWrap.style.display  = '';
        previewCap.textContent   = rows.length + ' students — review and confirm.';
        importBtn.disabled = false;
        importBtn.style.opacity = '1';

        rows.forEach(function (s, i) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="text-align:center;color:var(--text-tertiary);font-size:12px;">' + (i + 1) + '</td>' +
                '<td class="roll-cell" style="font-family:monospace;font-size:12px;color:var(--text-secondary);">' + rollFor(i) + '</td>' +
                '<td><input data-field="name" data-i="' + i + '" value="' + esc(s.name) + '" ' +
                '    style="border:none;background:transparent;width:100%;font-size:13px;color:var(--text);outline:none;"></td>' +
                '<td><input data-field="father_name" data-i="' + i + '" value="' + esc(s.father_name) + '" ' +
                '    style="border:none;background:transparent;width:100%;font-size:13px;color:var(--text-secondary);outline:none;"></td>' +
                '<td><button type="button" data-i="' + i + '" class="del-row" title="Remove" ' +
                '    style="color:#c42b1c;background:none;border:none;cursor:pointer;font-size:14px;padding:2px 6px;">&times;</button></td>';
            previewBody.appendChild(tr);
        });

        // Live edit
        previewBody.addEventListener('input', function (e) {
            var inp = e.target;
            if (!inp.dataset.field) return;
            rows[inp.dataset.i][inp.dataset.field] = inp.value;
        });

        // Delete row
        previewBody.addEventListener('click', function (e) {
            var btn = e.target.closest('.del-row');
            if (!btn) return;
            var i = parseInt(btn.dataset.i);
            rows.splice(i, 1);
            renderTable();
        });
    }

    function esc(s) {
        return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
    }

    /* ── Import ── */
    importBtn.addEventListener('click', function () {
        var dept  = deptSel.value;
        var sem   = semSel.value;
        var shift = document.getElementById('shiftSel').value;
        var pass  = document.getElementById('passInput').value;

        if (!dept)  { alert('Please select a department.'); return; }
        if (!sem)   { alert('Please select a semester.'); return; }
        if (!shift) { alert('Please select a shift.'); return; }
        if (!pass)  { alert('Please enter a default password.'); return; }
        if (rows.length === 0) { alert('No students to import.'); return; }

        if (!confirm('Import ' + rows.length + ' students into ' + dept + ' ' + sem + '?')) return;

        // Rebuild roll numbers from current state
        var finalRows = rows.map(function (s, i) {
            return { name: s.name, father_name: s.father_name };
        });

        document.getElementById('fDept').value     = dept;
        document.getElementById('fSem').value      = sem;
        document.getElementById('fShift').value    = shift;
        document.getElementById('fPass').value     = pass;
        document.getElementById('fRollPfx').value  = (rollPfx.value || 'MMI').toUpperCase();
        document.getElementById('fRollStart').value = rollStart.value || '1';
        document.getElementById('fJson').value     = JSON.stringify(finalRows);

        document.getElementById('importForm').submit();
    });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
