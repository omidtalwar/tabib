<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/departments.php';
require_role('teacher');

$pageTitle = 'Exam Papers — ' . SITE_NAME;
$user = current_user();

$tStmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id=?');
$tStmt->execute([$user['id']]);
$teacherId = $tStmt->fetchColumn();
if (!$teacherId) { die('Teacher profile not found.'); }

/** Detect English-language subject (uses the English exam template). */
function is_english_subject(string $s): bool {
    $s = mb_strtolower($s, 'UTF-8');
    foreach (['انګلیس', 'انگلیس', 'english'] as $kw) {
        if (mb_strpos($s, $kw) !== false) return true;
    }
    return false;
}

// ── POST handlers ─────────────────────────────────────────────────
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save' || $action === 'submit') {
        $id        = (int)($_POST['id'] ?? 0);
        $courseId  = (int)($_POST['teacher_course_id'] ?? 0);
        $examType  = in_array($_POST['exam_type'] ?? '', ['midterm','final']) ? $_POST['exam_type'] : 'final';
        $language  = in_array($_POST['language'] ?? '', ['pashto','english']) ? $_POST['language'] : 'pashto';
        $source    = ($_POST['source'] ?? 'form') === 'upload' ? 'upload' : 'form';
        $questions = trim($_POST['questions_json'] ?? '');

        // Resolve subject/class from the chosen course
        $cStmt = $pdo->prepare('SELECT * FROM teacher_courses WHERE id=? AND teacher_id=?');
        $cStmt->execute([$courseId, $teacherId]);
        $course = $cStmt->fetch();

        // Existing record (for keeping current file_path on edit)
        $existing = null;
        if ($id) {
            $eq = $pdo->prepare('SELECT * FROM exam_papers WHERE id=? AND teacher_id=?');
            $eq->execute([$id, $teacherId]);
            $existing = $eq->fetch() ?: null;
        }

        $err = '';
        $filePath = $existing['file_path'] ?? null;

        // Handle file upload (upload source only)
        if ($source === 'upload' && !empty($_FILES['exam_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['exam_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'docx') {
                $err = 'Only .docx files are accepted.';
            } elseif ($_FILES['exam_file']['size'] > 15 * 1024 * 1024) {
                $err = 'File must be under 15 MB.';
            } else {
                $dir = UPLOAD_DIR . 'exam_papers/';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fname = 'exampaper_' . uniqid('', true) . '.docx';
                if (move_uploaded_file($_FILES['exam_file']['tmp_name'], $dir . $fname)) {
                    // remove old file if replacing
                    if ($filePath && is_file(UPLOAD_DIR . $filePath)) @unlink(UPLOAD_DIR . $filePath);
                    $filePath = 'exam_papers/' . $fname;
                } else {
                    $err = 'File upload failed — check folder permissions.';
                }
            }
        }

        if (!$course) {
            $flash = ['err', 'Please select a valid class/subject.'];
        } elseif ($source === 'upload' && !$filePath) {
            $flash = ['err', $err ?: 'Please choose a .docx file to upload.'];
        } elseif ($err) {
            $flash = ['err', $err];
        } else {
            $status  = $action === 'submit' ? 'submitted' : 'draft';
            $subName = $course['subject_name'];
            if ($id) {
                $pdo->prepare(
                    'UPDATE exam_papers SET teacher_course_id=?, subject_name=?, department=?, semester=?, shift=?,
                        exam_type=?, language=?, source=?, file_path=?, questions=?, status=?,
                        submitted_at=' . ($action==='submit' ? 'NOW()' : 'submitted_at') . ', admin_note=NULL
                     WHERE id=? AND teacher_id=? AND status IN ("draft","rejected","submitted")'
                )->execute([$courseId, $subName, $course['department'], $course['semester'], $course['shift'],
                            $examType, $language, $source, $filePath, $questions, $status, $id, $teacherId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO exam_papers
                       (teacher_id, teacher_course_id, subject_name, department, semester, shift,
                        exam_type, language, source, file_path, questions, status, submitted_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,' . ($action==='submit' ? 'NOW()' : 'NULL') . ')'
                )->execute([$teacherId, $courseId, $subName, $course['department'], $course['semester'],
                            $course['shift'], $examType, $language, $source, $filePath, $questions, $status]);
            }
            try { require_once __DIR__.'/../includes/activity.php';
                  log_activity($pdo, 'exam_paper_'.$action, "$action exam paper: $subName"); } catch (Exception $e) {}
            $flash = ['ok', $action==='submit' ? 'Exam paper submitted to administration.' : 'Draft saved.'];
        }
    }

    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $fp = $pdo->prepare('SELECT file_path FROM exam_papers WHERE id=? AND teacher_id=?');
        $fp->execute([$id, $teacherId]);
        $delFile = $fp->fetchColumn();
        $pdo->prepare('DELETE FROM exam_papers WHERE id=? AND teacher_id=? AND status IN ("draft","rejected")')
            ->execute([$id, $teacherId]);
        if ($delFile && is_file(UPLOAD_DIR . $delFile)) @unlink(UPLOAD_DIR . $delFile);
        $flash = ['ok', 'Paper deleted.'];
    }

    $_SESSION['_ep_flash'] = $flash;
    header('Location: exam_papers.php' . ($action!=='delete' && !empty($_POST['stay_id']) ? '?id='.(int)$_POST['stay_id'] : ''));
    exit;
}
if (isset($_SESSION['_ep_flash'])) { $flash = $_SESSION['_ep_flash']; unset($_SESSION['_ep_flash']); }

// ── Teacher's classes/subjects for the dropdown ──────────────────
$courses = $pdo->prepare(
    'SELECT id, subject_name, department, semester, shift FROM teacher_courses
     WHERE teacher_id=? ORDER BY department, semester, shift, no'
);
$courses->execute([$teacherId]);
$courseList = $courses->fetchAll();

// ── Edit mode ─────────────────────────────────────────────────────
$editId = (int)($_GET['id'] ?? 0);
$edit = null;
if ($editId) {
    $e = $pdo->prepare('SELECT * FROM exam_papers WHERE id=? AND teacher_id=?');
    $e->execute([$editId, $teacherId]);
    $edit = $e->fetch() ?: null;
}
$editQuestions = $edit ? (json_decode($edit['questions'] ?? '', true) ?: []) : [];

// ── List of teacher's papers ──────────────────────────────────────
$papers = $pdo->prepare(
    'SELECT * FROM exam_papers WHERE teacher_id=? ORDER BY updated_at DESC'
);
$papers->execute([$teacherId]);
$paperList = $papers->fetchAll();

function epStatus(string $s): string {
    $m = [
        'draft'=>['Draft','background:var(--surface-hover);color:var(--text-secondary);'],
        'submitted'=>['Pending','background:rgba(15,108,189,.12);color:#0f6cbd;'],
        'approved'=>['Approved','background:rgba(14,122,14,.12);color:#0e7a0e;'],
        'rejected'=>['Rejected','background:rgba(196,43,28,.12);color:#c42b1c;'],
    ];
    [$l,$st]=$m[$s]??['—','']; return '<span class="fluent-badge" style="font-size:10px;'.$st.'">'.$l.'</span>';
}

// JS map: course id → meta (subject, dept en+ps, semester, shift, english flag)
$courseMeta = [];
foreach ($courseList as $c) {
    $courseMeta[$c['id']] = [
        'subject'  => $c['subject_name'],
        'dept'     => $c['department'],
        'dept_ps'  => dept_name_ps($pdo, $c['department']),
        'semester' => $c['semester'],
        'shift'    => $c['shift'],
        'english'  => is_english_subject($c['subject_name']),
    ];
}
$showForm = isset($_GET['id']) || isset($_GET['new']);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.q-block { border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:10px;background:var(--surface); }
.q-num   { font-size:11px;font-weight:700;color:var(--accent);margin-bottom:6px; }
.opt-row { display:flex;align-items:center;gap:8px;margin-top:6px; }
.opt-lbl { font-size:12px;font-weight:700;color:var(--text-tertiary);min-width:18px; }
</style>
<main id="mainContent" class="ml-56 mt-14 p-6 transition-all duration-300">

    <?php if ($flash): ?>
    <div class="fluent-alert <?= $flash[0]==='ok'?'fluent-alert-success':'fluent-alert-danger' ?> mb-4" data-flash>
        <?= htmlspecialchars($flash[1]) ?>
    </div>
    <?php endif; ?>

<?php if (!$showForm): ?>
    <!-- ════════ LIST ════════ -->
    <div class="flex items-center justify-between mb-5 fluent-fade-in">
        <div>
            <h1 class="fluent-h1">Exam Papers</h1>
            <p class="fluent-caption mt-1">Write your exam question papers and submit them to the administration.</p>
        </div>
        <a href="?new=1" class="fluent-btn-accent fluent-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Exam Paper
        </a>
    </div>

    <?php if (empty($paperList)): ?>
    <div class="fluent-card p-12 text-center fluent-fade-in" style="animation-delay:40ms;">
        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-tertiary);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p style="color:var(--text-tertiary);">No exam papers yet. Click "New Exam Paper" to write one.</p>
    </div>
    <?php else: ?>
    <div class="fluent-card overflow-hidden fluent-fade-in" style="animation-delay:40ms;">
        <table class="fluent-table">
            <thead>
                <tr><th>Subject</th><th>Class</th><th>Exam</th><th>Lang</th><th>Status</th><th style="width:160px;">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($paperList as $p): $qd = json_decode($p['questions'] ?? '', true) ?: []; ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($p['subject_name']) ?></td>
                <td style="font-size:12px;color:var(--text-secondary);">
                    <?= dept_label($pdo, $p['department']) ?><br><?= htmlspecialchars($p['semester'] ?? '') ?> · <?= htmlspecialchars($p['shift'] ?? '') ?>
                </td>
                <td><span class="fluent-badge <?= $p['exam_type']==='final'?'fluent-badge-success':'' ?>" style="text-transform:capitalize;"><?= $p['exam_type'] ?></span></td>
                <td style="font-size:12px;"><?= $p['language']==='english'?'EN':'PS' ?></td>
                <td><?= epStatus($p['status']) ?></td>
                <td>
                    <div class="flex gap-1">
                        <a href="?id=<?= (int)$p['id'] ?>" class="fluent-btn" style="padding:3px 10px;font-size:12px;">
                            <?= in_array($p['status'],['draft','rejected'])?'Edit':'View' ?>
                        </a>
                        <?php if (in_array($p['status'],['draft','rejected'])): ?>
                        <form method="POST" onsubmit="return confirm('Delete this paper?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="fluent-btn" style="padding:3px 10px;font-size:12px;color:#c42b1c;border-color:rgba(196,43,28,.3);">Del</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ════════ FORM ════════ -->
    <?php
    $readonly = $edit && in_array($edit['status'], ['submitted','approved']);
    $mcqs = $editQuestions['mcqs'] ?? [];
    $descs = $editQuestions['descriptive'] ?? [];
    ?>
    <div class="flex items-center gap-3 mb-5 fluent-fade-in">
        <a href="exam_papers.php" class="fluent-btn" style="padding:4px 10px;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="fluent-h1"><?= $edit ? ($readonly?'View':'Edit') : 'New' ?> Exam Paper</h1>
            <p class="fluent-caption mt-0.5">10 multiple-choice (2 marks each) + 10 descriptive (4 marks each).</p>
        </div>
        <?php if ($edit): ?><div class="ml-auto"><?= epStatus($edit['status']) ?></div><?php endif; ?>
    </div>

    <?php if ($edit && $edit['status']==='rejected' && $edit['admin_note']): ?>
    <div class="fluent-card p-4 mb-4" style="border-color:rgba(196,43,28,.3);">
        <p class="fluent-label mb-1" style="color:#c42b1c;">Admin feedback:</p>
        <p style="font-size:13px;color:var(--text-secondary);"><?= htmlspecialchars($edit['admin_note']) ?></p>
    </div>
    <?php endif; ?>

    <?php $curSource = $edit['source'] ?? 'form'; ?>
    <form method="POST" id="paperForm" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $editId ?>">
        <input type="hidden" name="stay_id" value="<?= $editId ?>">
        <input type="hidden" name="questions_json" id="questionsJson">
        <input type="hidden" name="source" id="sourceField" value="<?= htmlspecialchars($curSource) ?>">

        <!-- Method toggle -->
        <?php if (!$readonly): ?>
        <div class="fluent-card p-4 mb-4">
            <p class="fluent-label mb-2">How do you want to provide the exam?</p>
            <div class="flex gap-2">
                <button type="button" id="methodWrite" class="fluent-btn" onclick="setMethod('form')"
                        style="font-size:13px;<?= $curSource==='form'?'background:var(--accent);color:#fff;border-color:var(--accent);':'' ?>">
                    ✍ Write Questions
                </button>
                <button type="button" id="methodUpload" class="fluent-btn" onclick="setMethod('upload')"
                        style="font-size:13px;<?= $curSource==='upload'?'background:var(--accent);color:#fff;border-color:var(--accent);':'' ?>">
                    ⬆ Upload .docx File
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Meta -->
        <div class="fluent-card p-5 mb-4" style="<?= $readonly?'opacity:.7;pointer-events:none;':'' ?>">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="fluent-label block mb-1.5">Class &amp; Subject *</label>
                    <div class="fluent-input">
                        <select name="teacher_course_id" id="courseSel" required>
                            <option value="">— Select —</option>
                            <?php foreach ($courseList as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $edit && $edit['teacher_course_id']==$c['id']?'selected':'' ?>>
                                <?= htmlspecialchars($c['subject_name']) ?> — <?= dept_label($pdo,$c['department']) ?> <?= htmlspecialchars($c['semester']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Exam Type *</label>
                    <div class="fluent-input">
                        <select name="exam_type">
                            <option value="final"   <?= $edit && $edit['exam_type']==='final'?'selected':'' ?>>Final</option>
                            <option value="midterm" <?= $edit && $edit['exam_type']==='midterm'?'selected':'' ?>>Midterm</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="fluent-label block mb-1.5">Template Language</label>
                    <div class="fluent-input">
                        <select name="language" id="langSel">
                            <option value="pashto"  <?= $edit && $edit['language']==='pashto'?'selected':'' ?>>Pashto (پښتو)</option>
                            <option value="english" <?= $edit && $edit['language']==='english'?'selected':'' ?>>English</option>
                        </select>
                    </div>
                    <p style="font-size:11px;color:var(--text-tertiary);margin-top:4px;">Auto-set from subject; override if needed.</p>
                </div>
            </div>

            <!-- Auto-filled exam header (read-only, from the selected class) -->
            <div id="headerPreview" style="margin-top:16px;<?= $edit ? '' : 'display:none;' ?>">
                <p class="fluent-label mb-2" style="font-size:11px;">EXAM HEADER (auto from class — appears on the paper)</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="fluent-label block mb-1" style="font-size:10px;">مضمون · Subject</label>
                        <div class="fluent-input" style="background:var(--surface-hover);">
                            <input type="text" id="hSubject" readonly value="<?= $edit ? htmlspecialchars($edit['subject_name']) : '' ?>" style="font-weight:600;">
                        </div>
                    </div>
                    <div>
                        <label class="fluent-label block mb-1" style="font-size:10px;">څانګه · Department</label>
                        <div class="fluent-input" style="background:var(--surface-hover);">
                            <input type="text" id="hDept" readonly dir="rtl" value="<?= $edit ? htmlspecialchars(dept_name_ps($pdo, $edit['department'])) : '' ?>">
                        </div>
                    </div>
                    <div>
                        <label class="fluent-label block mb-1" style="font-size:10px;">سمستر · Semester</label>
                        <div class="fluent-input" style="background:var(--surface-hover);">
                            <input type="text" id="hSem" readonly value="<?= $edit ? htmlspecialchars($edit['semester'] ?? '') : '' ?>">
                        </div>
                    </div>
                    <div>
                        <label class="fluent-label block mb-1" style="font-size:10px;">شفټ · Shift</label>
                        <div class="fluent-input" style="background:var(--surface-hover);">
                            <input type="text" id="hShift" readonly value="<?= $edit ? htmlspecialchars($edit['shift'] ?? '') : '' ?>">
                        </div>
                    </div>
                </div>
                <p style="font-size:11px;color:var(--text-tertiary);margin-top:6px;">
                    Date, time, name, father name &amp; attendance number are added by the administration.
                </p>
            </div>
        </div>

        <!-- ═══ WRITE-QUESTIONS sections ═══ -->
        <div id="writeSections">
            <!-- MCQs -->
            <div class="fluent-card p-5 mb-4" style="<?= $readonly?'opacity:.7;pointer-events:none;':'' ?>">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="fluent-h2">Multiple-Choice Questions <span class="fluent-caption">(2 marks each)</span></h2>
                    <?php if (!$readonly): ?><button type="button" class="fluent-btn" style="font-size:12px;" onclick="addMcq()">+ Add MCQ</button><?php endif; ?>
                </div>
                <div id="mcqList"></div>
            </div>

            <!-- Descriptive -->
            <div class="fluent-card p-5 mb-4" style="<?= $readonly?'opacity:.7;pointer-events:none;':'' ?>">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="fluent-h2">Descriptive Questions <span class="fluent-caption">(4 marks each)</span></h2>
                    <?php if (!$readonly): ?><button type="button" class="fluent-btn" style="font-size:12px;" onclick="addDesc()">+ Add Question</button><?php endif; ?>
                </div>
                <div id="descList"></div>
            </div>
        </div>

        <!-- ═══ UPLOAD-FILE section ═══ -->
        <div id="uploadSection" class="fluent-card p-5 mb-4" style="display:none;<?= $readonly?'opacity:.7;pointer-events:none;':'' ?>">
            <h2 class="fluent-h2 mb-1">Upload Exam File</h2>
            <p class="fluent-caption mb-4">Upload a ready-made <strong>.docx</strong> exam file. Use the official template so the administration can fill student names for mailing.</p>

            <?php if (!empty($edit['file_path'])): ?>
            <div class="fluent-alert fluent-alert-success mb-3" style="font-size:13px;">
                Current file uploaded.
                <a href="<?= UPLOAD_URL . htmlspecialchars($edit['file_path']) ?>" style="color:var(--accent);margin-left:6px;" download>Download current file</a>
            </div>
            <?php endif; ?>

            <?php if (!$readonly): ?>
            <input type="file" name="exam_file" accept=".docx"
                   style="font-size:13px;padding:10px;border:2px dashed var(--border);border-radius:8px;width:100%;cursor:pointer;background:var(--surface);">
            <p style="font-size:11px;color:var(--text-tertiary);margin-top:6px;">.docx only · max 15 MB<?= !empty($edit['file_path']) ? ' · leave empty to keep the current file' : '' ?></p>
            <?php endif; ?>

            <p class="mt-3" style="font-size:12px;">
                <a href="<?= BASE_URL ?>/assets/exam-format.docx" download style="color:var(--accent);">⬇ Pashto template</a>
                &nbsp;·&nbsp;
                <a href="<?= BASE_URL ?>/assets/exam-format-english-subject.docx" download style="color:var(--accent);">⬇ English template</a>
            </p>
        </div>

        <?php if (!$readonly): ?>
        <div class="flex gap-2">
            <button type="submit" name="action" value="save" class="fluent-btn" style="font-size:14px;padding:9px 20px;">Save Draft</button>
            <button type="submit" name="action" value="submit" class="fluent-btn-accent fluent-btn" style="font-size:14px;padding:9px 20px;"
                    onclick="return confirm('Submit this exam paper to the administration?');">
                Submit for Approval
            </button>
        </div>
        <?php endif; ?>
    </form>

<?php endif; ?>
</main>

<?php if ($showForm): ?>
<script>
var READONLY = <?= $readonly ? 'true' : 'false' ?>;
var COURSE_META = <?= json_encode($courseMeta, JSON_UNESCAPED_UNICODE) ?>;
var existingMcqs  = <?= json_encode($mcqs ?: [], JSON_UNESCAPED_UNICODE) ?>;
var existingDescs = <?= json_encode($descs ?: [], JSON_UNESCAPED_UNICODE) ?>;

// Toggle between writing questions and uploading a file
function setMethod(m){
    document.getElementById('sourceField').value = m;
    var write = document.getElementById('writeSections');
    var upload = document.getElementById('uploadSection');
    var bw = document.getElementById('methodWrite');
    var bu = document.getElementById('methodUpload');
    var on = 'background:var(--accent);color:#fff;border-color:var(--accent);';
    if (m === 'upload') {
        if (write) write.style.display='none';
        if (upload) upload.style.display='';
        if (bw) bw.style.cssText='font-size:13px;'; if (bu) bu.style.cssText='font-size:13px;'+on;
    } else {
        if (write) write.style.display='';
        if (upload) upload.style.display='none';
        if (bu) bu.style.cssText='font-size:13px;'; if (bw) bw.style.cssText='font-size:13px;'+on;
    }
}

function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

function mcqBlock(i, d){
    d = d || {q:'',a:'',b:'',c:'',d:''};
    var ro = READONLY ? 'readonly' : '';
    return '<div class="q-block" data-mcq>'
      + '<div class="flex items-center justify-between"><span class="q-num">Question '+(i+1)+'</span>'
      + (READONLY?'':'<button type="button" onclick="this.closest(\'[data-mcq]\').remove();renumber()" style="color:#c42b1c;background:none;border:none;cursor:pointer;font-size:13px;">remove</button>')
      + '</div>'
      + '<input class="mq" '+ro+' value="'+esc(d.q)+'" placeholder="Question text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:7px 9px;font-size:13px;background:var(--surface);">'
      + ['a','b','c','d'].map(function(k){ return '<div class="opt-row"><span class="opt-lbl">'+k+'.</span>'
          + '<input class="mo-'+k+'" '+ro+' value="'+esc(d[k])+'" placeholder="Option '+k+'" style="flex:1;border:1px solid var(--border);border-radius:6px;padding:5px 9px;font-size:13px;background:var(--surface);"></div>'; }).join('')
      + '</div>';
}
function descBlock(i, d){
    d = d || {q:''};
    var ro = READONLY ? 'readonly' : '';
    return '<div class="q-block" data-desc>'
      + '<div class="flex items-center justify-between"><span class="q-num">Question '+(i+1)+'</span>'
      + (READONLY?'':'<button type="button" onclick="this.closest(\'[data-desc]\').remove();renumber()" style="color:#c42b1c;background:none;border:none;cursor:pointer;font-size:13px;">remove</button>')
      + '</div>'
      + '<textarea class="dq" '+ro+' rows="2" placeholder="Question text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:7px 9px;font-size:13px;background:var(--surface);resize:vertical;">'+esc(d.q)+'</textarea>'
      + '</div>';
}
function renumber(){
    document.querySelectorAll('#mcqList [data-mcq] .q-num').forEach(function(el,i){el.textContent='Question '+(i+1);});
    document.querySelectorAll('#descList [data-desc] .q-num').forEach(function(el,i){el.textContent='Question '+(i+1);});
}
function addMcq(d){ document.getElementById('mcqList').insertAdjacentHTML('beforeend', mcqBlock(document.querySelectorAll('#mcqList [data-mcq]').length, d)); }
function addDesc(d){ document.getElementById('descList').insertAdjacentHTML('beforeend', descBlock(document.querySelectorAll('#descList [data-desc]').length, d)); }

// Init: existing or 10 blanks each
(function(){
    var m = existingMcqs.length ? existingMcqs : Array(10).fill(null);
    var dd = existingDescs.length ? existingDescs : Array(10).fill(null);
    m.forEach(function(x){ addMcq(x); });
    dd.forEach(function(x){ addDesc(x); });

    // Class dropdown → fill header preview + auto language
    var cs = document.getElementById('courseSel');
    var IS_EDIT = <?= $edit?'true':'false' ?>;
    function fillHeader(id, setLang) {
        var meta = COURSE_META[id];
        var box = document.getElementById('headerPreview');
        if (!meta) { if (box) box.style.display = 'none'; return; }
        document.getElementById('hSubject').value = meta.subject || '';
        document.getElementById('hDept').value    = meta.dept_ps || '';
        document.getElementById('hSem').value     = meta.semester || '';
        document.getElementById('hShift').value   = meta.shift || '';
        if (box) box.style.display = '';
        if (setLang) document.getElementById('langSel').value = meta.english ? 'english' : 'pashto';
    }
    if (cs) {
        cs.addEventListener('change', function(){ fillHeader(this.value, !IS_EDIT); });
        if (cs.value) fillHeader(cs.value, false); // pre-fill on load
    }

    // Apply initial method (write vs upload)
    if (!READONLY) setMethod(document.getElementById('sourceField').value || 'form');
})();

// Serialize on submit
var pf = document.getElementById('paperForm');
if (pf) pf.addEventListener('submit', function(){
    var mcqs = [];
    document.querySelectorAll('#mcqList [data-mcq]').forEach(function(b){
        mcqs.push({ q:b.querySelector('.mq').value.trim(),
                    a:b.querySelector('.mo-a').value.trim(), b:b.querySelector('.mo-b').value.trim(),
                    c:b.querySelector('.mo-c').value.trim(), d:b.querySelector('.mo-d').value.trim() });
    });
    var descs = [];
    document.querySelectorAll('#descList [data-desc]').forEach(function(b){
        descs.push({ q:b.querySelector('.dq').value.trim() });
    });
    document.getElementById('questionsJson').value = JSON.stringify({mcqs:mcqs, descriptive:descs});
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
