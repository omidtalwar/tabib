<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/shamsi.php';

$user     = current_user();
$isAdmin  = ($user['role'] ?? '') === 'admin';
if (!$isAdmin) require_role('teacher');

$courseId = (int)($_GET['course_id'] ?? 0);
$examType = $_GET['exam_type'] ?? '';
$chance   = in_array($_GET['chance'] ?? '', ['first','second']) ? $_GET['chance'] : 'first';

$redirect = $isAdmin ? '../admin/results/' : 'exam_result.php';
if (!in_array($examType, ['midterm', 'final']) || !$courseId) {
    header('Location: ' . $redirect); exit;
}

if ($isAdmin) {
    // Admin can access any course — no ownership check
    $stmt = $pdo->prepare('SELECT * FROM teacher_courses WHERE id = ?');
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: ' . $redirect); exit; }
} else {
    // Teacher must own the course
    $stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $teacher = $stmt->fetch();
    if (!$teacher) { header('Location: ' . $redirect); exit; }

    $stmt = $pdo->prepare('SELECT * FROM teacher_courses WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$courseId, $teacher['id']]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: ' . $redirect); exit; }
}

// ── Pashto number words 0–20 (midterm) ───────────────────────────
function pashtoWord(?float $score): string {
    if ($score === null) return '';
    static $words = [
        0  => 'صفر',   1  => 'یو',      2  => 'دوه',    3  => 'درې',
        4  => 'څلور',  5  => 'پنځه',    6  => 'شپږ',    7  => 'اووه',
        8  => 'اته',   9  => 'نه',      10 => 'لس',     11 => 'یوولس',
        12 => 'دوولس', 13 => 'دیارلس',  14 => 'څوارلس', 15 => 'پنځلس',
        16 => 'شپاړس', 17 => 'اوولس',   18 => 'اتلس',   19 => 'نولس',
        20 => 'شل',
    ];
    $int  = (int)$score;
    $word = $words[$int] ?? (string)$int;
    if (abs($score - $int - 0.5) < 0.01) $word .= ' او نیم';
    return $word;
}

// ── Pashto number words 0–80 (final exam totals) ─────────────────
function pashtoWordFull(?float $score): string {
    if ($score === null) return '';
    static $words = [
        0  => 'صفر',        1  => 'یو',           2  => 'دوه',         3  => 'درې',
        4  => 'څلور',       5  => 'پنځه',         6  => 'شپږ',         7  => 'اووه',
        8  => 'اته',        9  => 'نه',           10 => 'لس',          11 => 'یوولس',
        12 => 'دوولس',      13 => 'دیارلس',       14 => 'څوارلس',      15 => 'پنځلس',
        16 => 'شپاړس',      17 => 'اوولس',        18 => 'اتلس',        19 => 'نولس',
        20 => 'شل',         21 => 'یویشت',        22 => 'دویشت',       23 => 'درویشت',
        24 => 'سلوویشت',    25 => 'پنځه ویشت',   26 => 'شپږ ویشت',    27 => 'اووه ویشت',
        28 => 'اته ویشت',   29 => 'نه ویشت',     30 => 'دیرش',        31 => 'یو دیرش',
        32 => 'دوه دیرش',   33 => 'درې دیرش',    34 => 'سلور دیرش',   35 => 'پنځه دیرش',
        36 => 'شپږ دیرش',   37 => 'اووه دیرش',   38 => 'اته دیرش',    39 => 'نه دیرش',
        40 => 'سلویشت',     41 => 'یو سلویشت',   42 => 'دوه سلویشت',  43 => 'درې سلویشت',
        44 => 'سلور سلویشت',45 => 'پنځه سلویشت', 46 => 'شپږ سلویشت',  47 => 'اووه سلویشت',
        48 => 'اته سلویشت', 49 => 'نه سلویشت',   50 => 'پنځوس',       51 => 'یو پنځوس',
        52 => 'دوه پنځوس',  53 => 'درې پنځوس',   54 => 'سلور پنځوس',  55 => 'پنځه پنځوس',
        56 => 'شپږ پنځوس',  57 => 'اووه پنځوس',  58 => 'اته پنځوس',   59 => 'نه پنځوس',
        60 => 'شپیته',      61 => 'یو شپیته',    62 => 'دوه شپیته',   63 => 'درې شپیته',
        64 => 'سلور شپیته', 65 => 'پنځه شپیته',  66 => 'شپږ شپیته',   67 => 'اووه شپیته',
        68 => 'اته شپیته',  69 => 'نه شپیته',    70 => 'اویا',        71 => 'یو اویا',
        72 => 'دوه اویا',   73 => 'درې اویا',    74 => 'سلور اویا',   75 => 'پنځه اویا',
        76 => 'شپږ اویا',   77 => 'اووه اویا',   78 => 'اته اویا',    79 => 'نه اویا',
        80 => 'اتیا',
    ];
    $int  = (int)$score;
    $word = $words[$int] ?? (string)$int;
    if (abs($score - $int - 0.5) < 0.01) $word .= ' او نیم';
    return $word;
}

// ── DOM helpers ───────────────────────────────────────────────────
const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

function xlFindCell(DOMXPath $xp, string $ref): ?DOMElement {
    $n = $xp->query("//ns:c[@r='$ref']");
    return $n->length ? $n->item(0) : null;
}

function xlSetInline(DOMDocument $dom, DOMXPath $xp, string $ref, string $val): void {
    $c = xlFindCell($xp, $ref);
    if (!$c) return;
    while ($c->hasChildNodes()) $c->removeChild($c->firstChild);
    $c->setAttribute('t', 'inlineStr');
    $is = $dom->createElement('is');
    $t  = $dom->createElement('t');
    $t->setAttribute('xml:space', 'preserve');
    $t->textContent = $val;
    $is->appendChild($t);
    $c->appendChild($is);
}

function xlSetNum(DOMDocument $dom, DOMXPath $xp, string $ref, $val): void {
    $c = xlFindCell($xp, $ref);
    if (!$c) return;
    while ($c->hasChildNodes()) $c->removeChild($c->firstChild);
    $c->removeAttribute('t');
    $c->appendChild($dom->createElement('v', (string)$val));
}

function xlUpdateSharedStr(DOMXPath $xp, int $idx, string $text): void {
    $sis = $xp->query('//ns:si');
    if ($idx >= $sis->length) return;
    $tn = $xp->query('ns:t', $sis->item($idx));
    if ($tn->length) $tn->item(0)->textContent = $text;
}

function xlClearCell(DOMXPath $xp, string $ref): void {
    $c = xlFindCell($xp, $ref);
    if (!$c) return;
    while ($c->hasChildNodes()) $c->removeChild($c->firstChild);
    $c->removeAttribute('t');
}

$chanceLabel = $chance === 'second' ? 'دویم چانس' : 'لومړی چانس';
$today       = shamsiDate();

// ═══════════════════════════════════════════════════════════════════
// MIDTERM — single-sheet shoqa
// ═══════════════════════════════════════════════════════════════════
if ($examType === 'midterm') {

    // Fetch students + midterm scores
    $stmt = $pdo->prepare(
        'SELECT s.roll_no, u.name, s.father_name, es.score
         FROM students s
         JOIN users u ON u.id = s.user_id
         LEFT JOIN exam_scores es
             ON es.student_id = s.id
             AND es.teacher_course_id = ?
             AND es.exam_type = "midterm"
         WHERE s.department = ? AND s.semester = ? AND s.shift = ?
         ORDER BY s.roll_no ASC'
    );
    $stmt->execute([$courseId,
        $course['department'], $course['semester'], $course['shift']]);
    $students = $stmt->fetchAll();

    $tplPath = __DIR__ . '/../assets/excel/midterm.xlsx';
    if (!file_exists($tplPath)) {
        http_response_code(404);
        die('Template not found: assets/excel/midterm.xlsx');
    }

    $tmpPath = sys_get_temp_dir() . '/shoqa_' . uniqid() . '.xlsx';
    copy($tplPath, $tmpPath);
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) { unlink($tmpPath); die('Cannot open template.'); }

    // Patch sharedStrings
    $ssDom = new DOMDocument('1.0', 'UTF-8');
    $ssDom->loadXML($zip->getFromName('xl/sharedStrings.xml'));
    $ssXp = new DOMXPath($ssDom);
    $ssXp->registerNamespace('ns', NS);
    xlUpdateSharedStr($ssXp, 12,
        ($course['department'] ?? '') . '  ' . ($course['semester'] ?? '') .
        '  (' . ($course['shift'] ?? '') . ')  شل فیصده ازموینې شقه  —  ' . $chanceLabel
    );
    xlUpdateSharedStr($ssXp, 13,
        'مضمون: ' . $course['subject_name'] .
        '     ممتحن: ' . $user['name'] .
        '     ازموینه: شل فیصده     تاریخ: ' . $today
    );
    $zip->addFromString('xl/sharedStrings.xml', $ssDom->saveXML());

    // Patch sheet
    $shDom = new DOMDocument('1.0', 'UTF-8');
    $shDom->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));
    $shXp = new DOMXPath($shDom);
    $shXp->registerNamespace('ns', NS);

    $startRow = 8; $rightMax = 39;
    foreach (array_slice($students, 0, 78) as $i => $s) {
        $score = $s['score'] !== null ? (float)$s['score'] : null;
        $word  = pashtoWord($score);
        if ($i < $rightMax) {
            $row = $startRow + $i;
            xlSetInline($shDom, $shXp, "I$row", $s['name']);
            xlSetInline($shDom, $shXp, "H$row", $s['father_name'] ?? '');
            if ($score !== null) {
                xlSetNum   ($shDom, $shXp, "G$row", $score);
                xlSetInline($shDom, $shXp, "F$row", $word);
            }
        } else {
            $row = $startRow + ($i - $rightMax);
            xlSetNum   ($shDom, $shXp, "E$row", $i + 1);
            xlSetInline($shDom, $shXp, "D$row", $s['name']);
            xlSetInline($shDom, $shXp, "C$row", $s['father_name'] ?? '');
            if ($score !== null) {
                xlSetNum   ($shDom, $shXp, "B$row", $score);
                xlSetInline($shDom, $shXp, "A$row", $word);
            }
        }
    }
    $zip->addFromString('xl/worksheets/sheet1.xml', $shDom->saveXML());
    $zip->close();

    $outName = 'shoqa_midterm_'
        . preg_replace('/[^a-z0-9]+/', '_', strtolower($course['subject_name']))
        . '_' . date('Ymd') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $outName . '"');
    header('Content-Length: ' . filesize($tmpPath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($tmpPath);
    unlink($tmpPath);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// FINAL — multi-sheet shoqa (all subjects + نتایج)
// ═══════════════════════════════════════════════════════════════════

// 1. All courses for this dept/sem/shift
$stmtAll = $pdo->prepare(
    'SELECT tc.*, u.name AS teacher_name
     FROM teacher_courses tc
     JOIN teachers t ON t.id = tc.teacher_id
     JOIN users u ON u.id = t.user_id
     WHERE tc.department = ? AND tc.semester = ? AND tc.shift = ?
     ORDER BY tc.no, tc.id'
);
$stmtAll->execute([$course['department'], $course['semester'], $course['shift']]);
$allCourses = $stmtAll->fetchAll();

// 2. All students for this dept/sem/shift
$stmtStu = $pdo->prepare(
    'SELECT s.id, s.roll_no, u.name, s.father_name
     FROM students s
     JOIN users u ON u.id = s.user_id
     WHERE s.department = ? AND s.semester = ? AND s.shift = ?
     ORDER BY s.roll_no'
);
$stmtStu->execute([$course['department'], $course['semester'], $course['shift']]);
$allStudents = $stmtStu->fetchAll();

// 3. Scores per course: [course_id][student_id][exam_type] = score
$courseScores = [];
foreach ($allCourses as $c) {
    $stmtSc = $pdo->prepare(
        'SELECT student_id, exam_type, score
         FROM exam_scores
         WHERE teacher_course_id = ? AND exam_type IN ("final", "midterm")'
    );
    $stmtSc->execute([$c['id']]);
    foreach ($stmtSc->fetchAll() as $es) {
        $courseScores[$c['id']][$es['student_id']][$es['exam_type']] = (float)$es['score'];
    }
}

$nCourses = min(count($allCourses), 8); // نتایج sheet has 8 subject columns (E–L)

// ── Open result.xlsx as base template ────────────────────────────
$tplPath = __DIR__ . '/../assets/excel/result.xlsx';
if (!file_exists($tplPath)) {
    http_response_code(404);
    die('Result template not found: assets/excel/result.xlsx');
}

$tmpPath = sys_get_temp_dir() . '/shoqa_final_' . uniqid() . '.xlsx';
copy($tplPath, $tmpPath);
$zip = new ZipArchive();
if ($zip->open($tmpPath) !== true) { unlink($tmpPath); die('Cannot open result template.'); }

// ── 1. Patch sharedStrings (نتایج sheet headers) ─────────────────
$ssDom = new DOMDocument('1.0', 'UTF-8');
$ssDom->loadXML($zip->getFromName('xl/sharedStrings.xml'));
$ssXp = new DOMXPath($ssDom);
$ssXp->registerNamespace('ns', NS);

// Index 5: result sheet title
xlUpdateSharedStr($ssXp, 5,
    'د روان ' . shamsiDateNumeric() . ' کال  د ' .
    ($course['department'] ?? '') . '  ' . ($course['semester'] ?? '') .
    ' سمستر  ' . ($course['shift'] ?? '') .
    '  دوره  د نهايي ازمویني د نتایجو او نمراتو د تعین جدول'
);
// Indices 13–20: subject column headers in نتایج sheet
for ($ci = 0; $ci < 8; $ci++) {
    xlUpdateSharedStr($ssXp, 13 + $ci,
        $ci < $nCourses ? $allCourses[$ci]['subject_name'] : ''
    );
}
$zip->addFromString('xl/sharedStrings.xml', $ssDom->saveXML());

// ── 2. Fill نتایج sheet (sheet1.xml) ─────────────────────────────
$shResult = new DOMDocument('1.0', 'UTF-8');
$shResult->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));
$shRXp = new DOMXPath($shResult);
$shRXp->registerNamespace('ns', NS);

$subjCols    = ['E','F','G','H','I','J','K','L'];
$nAllStudents = count($allStudents);

for ($idx = 0; $idx < 20; $idx++) {   // template has 20 data rows (rows 10–29)
    $row = 10 + $idx;
    if ($idx < $nAllStudents) {
        $stu = $allStudents[$idx];
        xlSetNum   ($shResult, $shRXp, "O$row", $idx + 1);
        xlSetInline($shResult, $shRXp, "N$row", $stu['name']);
        xlSetInline($shResult, $shRXp, "M$row", $stu['father_name'] ?? '');
        foreach ($allCourses as $ci2 => $rc) {
            if ($ci2 >= 8) break;
            $scores  = $courseScores[$rc['id']][$stu['id']] ?? [];
            $final   = isset($scores['final'])   ? (float)$scores['final']   : null;
            $midterm = isset($scores['midterm']) ? (float)$scores['midterm'] : null;
            $total   = ($final !== null || $midterm !== null)
                     ? (($final ?? 0) + ($midterm ?? 0)) : null;
            if ($total !== null) xlSetNum($shResult, $shRXp, $subjCols[$ci2] . $row, $total);
            else                 xlClearCell($shRXp, $subjCols[$ci2] . $row);
        }
        // Clear columns for subjects that don't exist
        for ($ci2 = $nCourses; $ci2 < 8; $ci2++) {
            xlClearCell($shRXp, $subjCols[$ci2] . $row);
        }
    } else {
        // No student at this row — clear everything
        xlClearCell($shRXp, "O$row");
        xlClearCell($shRXp, "N$row");
        xlClearCell($shRXp, "M$row");
        foreach ($subjCols as $col) xlClearCell($shRXp, $col . $row);
    }
}
$zip->addFromString('xl/worksheets/sheet1.xml', $shResult->saveXML());

// ── 3. Build subject sheets ───────────────────────────────────────
/*
 * result.xlsx already has sheet2.xml (subject 1) and sheet3.xml (subject 2).
 * We use sheet2.xml as the template for every subject sheet (same structure,
 * correct shared-string indices for result.xlsx).
 *
 * Subject sheet layout (RTL, 18 cols A–R), data rows 13–50:
 *   RIGHT side (students 1–38):
 *     R=serial(pre-filled), Q=name, P=father, N=20%, M=60%, L=total, K=total-words
 *   LEFT side (students 39–76):
 *     I=serial, H=name, G=father, E=20%, D=60%, C=total, B=total-words
 */
$subjectTpl  = $zip->getFromName('xl/worksheets/sheet2.xml'); // template XML

// _rels for new sheets (3+): reuse drawing2.xml (same logo)
$newSheetRels =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
    '<Relationship Id="rId1" ' .
        'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" ' .
        'Target="../drawings/drawing2.xml"/>' .
    '</Relationships>';

for ($ci = 0; $ci < $nCourses; $ci++) {
    $c        = $allCourses[$ci];
    $sheetNum = $ci + 2; // sheet2, sheet3, sheet4, …
    $sheetFile = "xl/worksheets/sheet{$sheetNum}.xml";

    $shDom = new DOMDocument('1.0', 'UTF-8');
    $shDom->loadXML($subjectTpl);
    $shXp = new DOMXPath($shDom);
    $shXp->registerNamespace('ns', NS);

    // Dynamic header cells (convert shared-string → inlineStr)
    xlSetInline($shDom, $shXp, 'B7', 'وخت : ' . ($c['shift']      ?? '') . '     چانس: ' . $chanceLabel);
    xlSetInline($shDom, $shXp, 'G7', 'سمستر : ' . ($c['semester']  ?? ''));
    xlSetInline($shDom, $shXp, 'L7', $c['department'] ?? '');
    xlSetInline($shDom, $shXp, 'Q7', $today);
    xlSetInline($shDom, $shXp, 'A9', $today);
    xlSetInline($shDom, $shXp, 'H9', 'ازموینه: شصت فیصده');
    xlSetInline($shDom, $shXp, 'K9', 'استاد ' . ($c['teacher_name'] ?? ''));
    xlSetInline($shDom, $shXp, 'O9', $c['subject_name']);

    // Student data — iterate every row on both halves; fill real data or clear template data
    $startRow = 13; $rightMax = 38;
    for ($ri = 0; $ri < $rightMax; $ri++) {
        $row = $startRow + $ri;

        // ── RIGHT side (students 1–38) ──────────────────────────────
        if ($ri < count($allStudents)) {
            $stu     = $allStudents[$ri];
            $scores  = $courseScores[$c['id']][$stu['id']] ?? [];
            $final   = isset($scores['final'])   ? (float)$scores['final']   : null;
            $midterm = isset($scores['midterm']) ? (float)$scores['midterm'] : null;
            $total   = ($final !== null || $midterm !== null)
                     ? (($final ?? 0) + ($midterm ?? 0)) : null;
            xlSetNum   ($shDom, $shXp, "R$row", $ri + 1);
            xlSetInline($shDom, $shXp, "Q$row", $stu['name']);
            xlSetInline($shDom, $shXp, "P$row", $stu['father_name'] ?? '');
            xlSetNum   ($shDom, $shXp, "O$row", 0);       // attendance not tracked
            if ($final   !== null) xlSetNum($shDom, $shXp, "M$row", $final);
            else                   xlClearCell($shXp, "M$row");
            if ($midterm !== null) xlSetNum($shDom, $shXp, "N$row", $midterm);
            else                   xlClearCell($shXp, "N$row");
            if ($total   !== null) {
                xlSetNum   ($shDom, $shXp, "L$row", $total);
                xlSetInline($shDom, $shXp, "K$row", pashtoWordFull($total));
            } else {
                xlClearCell($shXp, "L$row");
                xlClearCell($shXp, "K$row");
            }
        } else {
            // Empty right-side row — wipe template data
            foreach (['R','Q','P','O','N','M','L','K'] as $col) {
                xlClearCell($shXp, $col . $row);
            }
        }

        // ── LEFT side (students 39–76) ──────────────────────────────
        $li = $ri + $rightMax;
        if ($li < count($allStudents)) {
            $stu     = $allStudents[$li];
            $scores  = $courseScores[$c['id']][$stu['id']] ?? [];
            $final   = isset($scores['final'])   ? (float)$scores['final']   : null;
            $midterm = isset($scores['midterm']) ? (float)$scores['midterm'] : null;
            $total   = ($final !== null || $midterm !== null)
                     ? (($final ?? 0) + ($midterm ?? 0)) : null;
            xlSetNum   ($shDom, $shXp, "I$row", $li + 1);
            xlSetInline($shDom, $shXp, "H$row", $stu['name']);
            xlSetInline($shDom, $shXp, "G$row", $stu['father_name'] ?? '');
            xlSetNum   ($shDom, $shXp, "F$row", 0);       // attendance not tracked
            if ($final   !== null) xlSetNum($shDom, $shXp, "D$row", $final);
            else                   xlClearCell($shXp, "D$row");
            if ($midterm !== null) xlSetNum($shDom, $shXp, "E$row", $midterm);
            else                   xlClearCell($shXp, "E$row");
            if ($total   !== null) {
                xlSetNum   ($shDom, $shXp, "C$row", $total);
                xlSetInline($shDom, $shXp, "B$row", pashtoWordFull($total));
            } else {
                xlClearCell($shXp, "C$row");
                xlClearCell($shXp, "B$row");
            }
        } else {
            // Empty left-side row — wipe template data
            foreach (['I','H','G','F','E','D','C','B'] as $col) {
                xlClearCell($shXp, $col . $row);
            }
        }
    }

    $zip->addFromString($sheetFile, $shDom->saveXML());

    // Add _rels for sheets beyond the two that exist in result.xlsx
    if ($sheetNum >= 4) {
        $zip->addFromString(
            "xl/worksheets/_rels/sheet{$sheetNum}.xml.rels",
            $newSheetRels
        );
    }
}

// ── 4. Update workbook.xml ────────────────────────────────────────
// Use rId100+ for worksheets to avoid conflicting with existing
// non-worksheet rels (rId4=theme, rId5=styles, rId6=sharedStrings).
$wbXml = $zip->getFromName('xl/workbook.xml');

// Rebuild <sheets> block
$newSheets = '<sheets>';
$newSheets .= '<sheet name="نتایج" sheetId="1" r:id="rId100"/>';
for ($ci = 0; $ci < $nCourses; $ci++) {
    $name    = htmlspecialchars($allCourses[$ci]['subject_name'], ENT_XML1 | ENT_QUOTES);
    $sheetId = $ci + 2;
    $rId     = 101 + $ci;
    $newSheets .= "<sheet name=\"{$name}\" sheetId=\"{$sheetId}\" r:id=\"rId{$rId}\"/>";
}
$newSheets .= '</sheets>';
$wbXml = preg_replace('/<sheets>.*?<\/sheets>/s', $newSheets, $wbXml);

// Rebuild <definedNames> block (print-row titles)
$newDefined = '<definedNames>';
$newDefined .= '<definedName name="_xlnm.Print_Titles" localSheetId="0">نتایج!$1:$9</definedName>';
for ($ci = 0; $ci < $nCourses; $ci++) {
    $sn = str_replace("'", "''", $allCourses[$ci]['subject_name']);
    $newDefined .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . ($ci + 1) . '">' .
                   "'" . htmlspecialchars($sn, ENT_XML1) . "'!\$1:\$5" .
                   '</definedName>';
}
$newDefined .= '</definedNames>';
$wbXml = preg_replace('/<definedNames>.*?<\/definedNames>/s', $newDefined, $wbXml);

$zip->addFromString('xl/workbook.xml', $wbXml);

// ── 5. Update workbook.xml.rels ───────────────────────────────────
$relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

// Remove old worksheet and calcChain relationships.
// [^>]* (not [^\/]*) is required because Target="worksheets/sheet1.xml" contains '/'.
$relXml = preg_replace(
    '/<Relationship[^>]*Type="[^"]*\/(worksheet|calcChain)"[^>]*\/>/s',
    '',
    $relXml
);

// Inject new worksheet rels (rId100 = نتایج, rId101+ = subjects)
$newRels = '<Relationship Id="rId100"'
    . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
    . ' Target="worksheets/sheet1.xml"/>';
for ($ci = 0; $ci < $nCourses; $ci++) {
    $rId      = 101 + $ci;
    $sheetNum = $ci + 2;
    $newRels .= "<Relationship Id=\"rId{$rId}\""
        . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
        . " Target=\"worksheets/sheet{$sheetNum}.xml\"/>";
}
$relXml = str_replace('</Relationships>', $newRels . '</Relationships>', $relXml);

$zip->addFromString('xl/_rels/workbook.xml.rels', $relXml);

// ── 6. Update [Content_Types].xml ────────────────────────────────
$ctXml = $zip->getFromName('[Content_Types].xml');

// Remove existing worksheet and calcChain overrides.
// [^>]* is required because ContentType="application/vnd...." contains '/'.
$ctXml = preg_replace(
    '/<Override PartName="\/xl\/worksheets\/sheet\d+\.xml"[^>]*\/>/s',
    '',
    $ctXml
);
$ctXml = preg_replace(
    '/<Override PartName="\/xl\/calcChain\.xml"[^>]*\/>/s',
    '',
    $ctXml
);

// Inject new worksheet overrides before </Types>
$newCt  = '<Override PartName="/xl/worksheets/sheet1.xml"'
        . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
for ($ci = 0; $ci < $nCourses; $ci++) {
    $sheetNum = $ci + 2;
    $newCt .= "<Override PartName=\"/xl/worksheets/sheet{$sheetNum}.xml\""
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$ctXml = str_replace('</Types>', $newCt . '</Types>', $ctXml);

$zip->addFromString('[Content_Types].xml', $ctXml);

$zip->close();

// ── Stream download ───────────────────────────────────────────────
$dept = preg_replace('/[^a-z0-9]+/', '_', strtolower($course['department'] ?? 'final'));
$outName = 'shoqa_final_' . $dept . '_' . date('Ymd') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $outName . '"');
header('Content-Length: ' . filesize($tmpPath));
header('Cache-Control: no-cache, must-revalidate');
readfile($tmpPath);
unlink($tmpPath);
exit;
