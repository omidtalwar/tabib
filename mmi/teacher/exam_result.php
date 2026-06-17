<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pageTitle = 'Exam Result — ' . SITE_NAME;
$user = current_user();

$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

$courses = [];
if ($teacher) {
    $stmt = $pdo->prepare('SELECT * FROM teacher_courses WHERE teacher_id = ? ORDER BY no, id');
    $stmt->execute([$teacher['id']]);
    $courses = $stmt->fetchAll();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <div class="mb-5 fluent-fade-in">
        <h1 class="fluent-h1">Exam Result</h1>
        <p class="fluent-caption mt-1">Select a subject to configure and generate exam results.</p>
    </div>

    <?php if (empty($courses)): ?>
    <div class="fluent-card p-10 text-center fluent-fade-in">
        <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="fluent-body" style="color:var(--text-tertiary);">No courses assigned yet.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:60ms;">
        <table class="fluent-table">
            <thead>
                <tr>
                    <th style="width:60px;">No.</th>
                    <th>Subject Name</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Shift</th>
                    <th>Credits</th>
                    <th style="width:110px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
            <tr>
                <td style="color:var(--text-tertiary);font-weight:600;"><?= (int)$c['no'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($c['subject_name']) ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['department'] ?? '—') ?></td>
                <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['semester'] ?? '—') ?></td>
                <td>
                    <?php if ($c['shift']): ?>
                    <span class="fluent-badge"><?= htmlspecialchars($c['shift']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <span class="fluent-badge fluent-badge-success"><?= (int)$c['credits'] ?> cr</span>
                </td>
                <td>
                    <button type="button"
                            class="fluent-btn open-exam-modal"
                            data-id="<?= (int)$c['id'] ?>"
                            data-subject="<?= htmlspecialchars($c['subject_name'], ENT_QUOTES) ?>"
                            data-dept="<?= htmlspecialchars($c['department'] ?? '', ENT_QUOTES) ?>"
                            data-semester="<?= htmlspecialchars($c['semester'] ?? '', ENT_QUOTES) ?>"
                            style="padding:4px 12px;font-size:12px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 30%,transparent);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Result
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<!-- ============================================================
     EXAM RESULT MODAL
     ============================================================ -->
<div id="examModal"
     class="fixed inset-0 z-50 flex items-center justify-center hidden"
     style="background: rgba(0,0,0,0.35); backdrop-filter: blur(4px);">

    <div class="fluent-card w-full max-w-md mx-4 fluent-fade-in" style="box-shadow: var(--shadow-lg);">

        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4"
             style="border-bottom: 1px solid var(--border);">
            <div>
                <h2 class="fluent-h3" id="modalSubjectName">Subject Name</h2>
                <p class="fluent-caption mt-0.5" id="modalSubjectMeta"></p>
            </div>
            <button id="closeModal"
                    class="w-8 h-8 rounded-md flex items-center justify-center transition"
                    style="color:var(--text-tertiary);"
                    onmouseover="this.style.background='var(--hover)'"
                    onmouseout="this.style.background='transparent'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Modal body -->
        <div class="px-6 py-5 space-y-5">

            <!-- Exam Type -->
            <div>
                <label class="fluent-label block mb-1.5">Exam Type</label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <select id="examType">
                        <option value="">— Select exam type —</option>
                        <option value="midterm">Midterm Exam</option>
                        <option value="final">Final Exam</option>
                    </select>
                </div>
            </div>

            <!-- Exam Repeat / Chance -->
            <div>
                <label class="fluent-label block mb-1.5">Exam Repeat</label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <select id="examChance">
                        <option value="first">First Chance Exam</option>
                        <option value="second">Second Chance Exam</option>
                    </select>
                </div>
            </div>

            <!-- Sort By -->
            <div>
                <label class="fluent-label block mb-1.5">Sort By
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:11px;color:var(--text-tertiary);"> — order students appear in result</span>
                </label>
                <div class="fluent-input">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--text-tertiary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>
                    </svg>
                    <select id="sortBy">
                        <option value="roll_no">Roll Number</option>
                        <option value="name">Student Name (A–Z)</option>
                        <option value="marks_desc">Marks (High to Low)</option>
                        <option value="marks_asc">Marks (Low to High)</option>
                    </select>
                </div>
            </div>

            <!-- Output Type -->
            <div>
                <label class="fluent-label block mb-2">Output Type</label>
                <div class="flex gap-2 flex-wrap" id="outputTypeGroup">
                    <?php foreach ([
                        ['screen', 'On Screen',   'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['pdf',    'PDF',          'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                        ['excel',  'Excel',        'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ] as [$val, $label, $path]): ?>
                    <label class="output-option flex items-center gap-2 px-3 py-2 rounded-md cursor-pointer transition"
                           style="border:1px solid var(--border); font-size:13px; color:var(--text-secondary);"
                           data-value="<?= $val ?>">
                        <input type="radio" name="outputType" value="<?= $val ?>" class="hidden"
                               <?= $val === 'screen' ? 'checked' : '' ?>>
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/>
                        </svg>
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Modal footer — action buttons -->
        <div class="flex items-center gap-2 px-6 py-4" style="border-top:1px solid var(--border);">

            <button id="btnProceed" class="fluent-btn-accent fluent-btn" style="font-size:13px;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Proceed
            </button>

            <button id="btnShoqa" class="fluent-btn" style="font-size:13px;color:var(--accent);border-color:color-mix(in srgb,var(--accent) 35%,transparent);">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Shoqa
            </button>


        </div>
    </div>
</div>

<script>
(function () {
    let currentCourseId = 0;

    // ── Open modal ──────────────────────────────────────────────
    document.querySelectorAll('.open-exam-modal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentCourseId = this.dataset.id;
            const subject   = this.dataset.subject;
            const dept      = this.dataset.dept;
            const semester  = this.dataset.semester;

            document.getElementById('modalSubjectName').textContent = subject;
            const meta = [dept, semester].filter(Boolean).join(' · ');
            document.getElementById('modalSubjectMeta').textContent = meta || 'No department / semester set';

            // Reset fields
            document.getElementById('examType').value   = '';
            document.getElementById('examChance').value = 'first';
            document.getElementById('sortBy').value     = 'roll_no';
            setOutputType('screen');

            document.getElementById('examModal').classList.remove('hidden');
        });
    });

    // ── Close modal ─────────────────────────────────────────────
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('examModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
    function closeModal() {
        document.getElementById('examModal').classList.add('hidden');
    }

    // ── Output type pill toggle ──────────────────────────────────
    function setOutputType(val) {
        document.querySelectorAll('.output-option').forEach(function (el) {
            const active = el.dataset.value === val;
            el.style.background     = active ? 'color-mix(in srgb,var(--accent) 10%,transparent)' : 'transparent';
            el.style.borderColor    = active ? 'color-mix(in srgb,var(--accent) 40%,transparent)' : 'var(--border)';
            el.style.color          = active ? 'var(--accent)' : 'var(--text-secondary)';
            el.style.fontWeight     = active ? '600' : '400';
            el.querySelector('input').checked = active;
        });
    }
    document.querySelectorAll('.output-option').forEach(function (el) {
        el.addEventListener('click', function () { setOutputType(this.dataset.value); });
    });
    setOutputType('screen');

    // ── Action buttons ───────────────────────────────────────────
    function getSelections() {
        return {
            examType:   document.getElementById('examType').value,
            examChance: document.getElementById('examChance').value,
            sortBy:     document.getElementById('sortBy').value,
            outputType: document.querySelector('input[name="outputType"]:checked')?.value || 'screen',
            subject:    document.getElementById('modalSubjectName').textContent,
        };
    }

    document.getElementById('btnProceed').addEventListener('click', function () {
        const s = getSelections();
        if (!s.examType) {
            alert('Please select an exam type first.');
            return;
        }
        const url = 'exam_entry.php'
            + '?course_id=' + encodeURIComponent(currentCourseId)
            + '&exam_type='  + encodeURIComponent(s.examType)
            + '&chance='     + encodeURIComponent(s.examChance)
            + '&sort_by='    + encodeURIComponent(s.sortBy);
        window.location.href = url;
    });

    document.getElementById('btnShoqa').addEventListener('click', function () {
        const s = getSelections();
        if (!s.examType) { alert('Please select an exam type first.'); return; }
        // TODO: open Shoqa result card view
        alert('Generating Shoqa for: ' + s.subject + ' — ' + s.examType);
    });

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
