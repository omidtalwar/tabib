<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/departments.php';
require_role('admin');

$id      = (int)($_GET['id'] ?? 0);
$preview = isset($_GET['preview']);
$mailing = isset($_GET['mailing']);

$stmt = $pdo->prepare(
    'SELECT ep.*, u.name AS teacher_name
     FROM exam_papers ep
     JOIN teachers t ON t.id=ep.teacher_id
     JOIN users u    ON u.id=t.user_id
     WHERE ep.id=?'
);
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); die('Exam paper not found.'); }

$q     = json_decode($p['questions'] ?? '', true) ?: [];
$mcqs  = $q['mcqs'] ?? [];
$descs = $q['descriptive'] ?? [];
$isEn  = $p['language'] === 'english';

$isUpload = (($p['source'] ?? 'form') === 'upload')
    && !empty($p['file_path']) && is_file(UPLOAD_DIR . $p['file_path']);

$deptPs = dept_name_ps($pdo, $p['department']);
$date   = $p['exam_date'] ?: '';
$time   = $p['shift'] ?: '';

$mcqHead  = $isEn ? 'MCQs' : 'څلور ځوابه پوښتنې';
$mcqNote  = $isEn ? 'Note: every question carries equal marks (2)' : 'نوټ: هره پوښتنه دوه (۲) نمرې لري';
$descHead = $isEn ? 'Descriptive Questions' : 'تشریحي پوښتنې';
$descNote = $isEn ? 'Note: every question carries equal marks (4)' : 'نوټ: هره پوښتنه څلور (۴) نمرې لري';
$qWord    = $isEn ? 'Question' : 'سوال';
$optKeys  = $isEn ? ['a','b','c','d'] : ['الف','ب','ج','د'];
$optMap   = ['a','b','c','d'];

function xe(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }

// ── Common header values shared by all papers ────────────────────
$commonValues = [
    9  => $date,                // تاریخ
    11 => $p['subject_name'],   // مضمون
    12 => $p['teacher_name'],   // استاد
    13 => $time,                // وخت (shift)
    14 => $p['semester'] ?? '', // سمستر
    15 => $deptPs,              // څانګه
];

// ════════════════════════════════════════════════════════════════
// PREVIEW
// ════════════════════════════════════════════════════════════════
if ($preview) {
    if ($isUpload) {
        // Uploaded .docx can't render as HTML — serve the raw file
        header('Location: ' . UPLOAD_URL . rawurlencode_path($p['file_path']));
        exit;
    }
    $instLines = ['د عامې روغتیا وزارت','د خصوصي روغتیایې علومو انستېتوتونو ریاست','مومن د طبي علومو انستېتوت','علمي او تدریسی معاونیت — د ازموینو کمیټه'];
    $examWord  = $p['exam_type'] === 'final' ? 'نهايي' : 'منځنۍ';
    $titleLine = 'د ' . ($p['year_label'] ?: '۱۴۰۵ هـ ش') . ' کال ' . ($p['term_label'] ?: 'بهاري سمستر') . " د $examWord ازموینو سوالونو پاڼه";
    $info = ['تاریخ'=>$date,'حاضری نمبر'=>'____','مضمون'=>$p['subject_name'],'استاد'=>$p['teacher_name'],'وخت'=>$time,'سمستر'=>$p['semester'] ?? '','څانګه'=>$deptPs,'د پلار نوم'=>'________','نوم'=>'________'];
    $dir = $isEn ? 'ltr' : 'rtl'; $align = $isEn ? 'left' : 'right';
    header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html><html lang="ps" dir="rtl"><head><meta charset="utf-8"><title>Exam Paper Preview</title>
<style> body{font-family:'Arial','Tahoma',sans-serif;max-width:820px;margin:24px auto;padding:0 30px;color:#111;}
 .inst{text-align:center;line-height:1.9;} .inst .t1{font-size:13px;} .inst .title{font-size:16px;font-weight:bold;margin:8px 0;}
 table.info{width:100%;border-collapse:collapse;margin:14px 0;direction:rtl;} table.info td{border:1px solid #999;padding:5px 8px;font-size:13px;} table.info td.lbl{font-weight:bold;background:#f3f3f3;white-space:nowrap;}
 .sec{font-weight:bold;font-size:15px;margin:18px 0 4px;border-bottom:2px solid #333;padding-bottom:3px;direction:<?= $dir ?>;text-align:<?= $align ?>;}
 .note{font-size:12px;color:#444;margin-bottom:10px;direction:<?= $dir ?>;text-align:<?= $align ?>;}
 .q{margin:10px 0;direction:<?= $dir ?>;text-align:<?= $align ?>;font-size:13px;} .q .qt{font-weight:600;}
 .opts{display:flex;flex-wrap:wrap;gap:6px 24px;margin-top:4px;padding-<?= $isEn?'left':'right' ?>:18px;} .opt{font-size:13px;min-width:40%;}
 @media print{.noprint{display:none;}body{margin:0;}}</style></head><body>
<div class="noprint" style="text-align:center;margin-bottom:14px;"><button onclick="window.print()" style="padding:8px 18px;font-size:14px;cursor:pointer;">🖨 Print</button>
<span style="font-size:12px;color:#888;margin-left:10px;">The downloaded .docx uses the official template with logo.</span></div>
<div class="inst"><?php foreach ($instLines as $l): ?><div class="t1"><?= htmlspecialchars($l) ?></div><?php endforeach; ?><div class="title"><?= htmlspecialchars($titleLine) ?></div></div>
<table class="info"><tr><?php $i=0; foreach ($info as $lbl=>$val): ?><td class="lbl"><?= htmlspecialchars($lbl) ?></td><td><?= htmlspecialchars($val ?: '________') ?></td><?php if (++$i%3===0): ?></tr><tr><?php endif; ?><?php endforeach; ?></tr></table>
<div class="sec"><?= htmlspecialchars($mcqHead) ?></div><div class="note"><?= htmlspecialchars($mcqNote) ?></div>
<?php foreach ($mcqs as $n=>$m): ?><div class="q"><span class="qt"><?= ($n+1) ?>. <?= $qWord ?>:</span> <?= htmlspecialchars($m['q'] ?? '') ?><div class="opts"><?php foreach ($optMap as $k=>$dk): ?><span class="opt"><?= $optKeys[$k] ?>) <?= htmlspecialchars($m[$dk] ?? '') ?></span><?php endforeach; ?></div></div><?php endforeach; ?>
<div class="sec"><?= htmlspecialchars($descHead) ?></div><div class="note"><?= htmlspecialchars($descNote) ?></div>
<?php foreach ($descs as $n=>$d): ?><div class="q"><span class="qt"><?= ($n+1) ?>. <?= $qWord ?>:</span> <?= htmlspecialchars($d['q'] ?? '') ?></div><?php endforeach; ?>
</body></html>
    <?php exit;
}

// ════════════════════════════════════════════════════════════════
// DOCX helpers
// ════════════════════════════════════════════════════════════════
function bpara(string $text, array $opts = []): string {
    $rtl=$opts['rtl']??true; $bold=!empty($opts['bold']); $size=$opts['size']??24; $align=$opts['align']??($rtl?'right':'left');
    $pPr='<w:pPr>'.($rtl?'<w:bidi/>':'').'<w:jc w:val="'.$align.'"/><w:spacing w:after="40" w:line="264" w:lineRule="auto"/></w:pPr>';
    $rPr='<w:rPr>'.($rtl?'<w:rtl/>':'').($bold?'<w:b/><w:bCs/>':'').'<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/></w:rPr>';
    return '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.xe($text).'</w:t></w:r></w:p>';
}

function injectHeader(string $headerXml, array $values): string {
    $ci = -1;
    return preg_replace_callback('#<w:tc>(.*?)</w:tc>#s', function ($mm) use (&$ci, $values) {
        $ci++;
        if (empty($values[$ci])) return $mm[0];
        $run = '<w:r><w:rPr><w:rtl/><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
             . '<w:sz w:val="20"/><w:szCs w:val="20"/><w:b/><w:bCs/></w:rPr>'
             . '<w:t xml:space="preserve">' . xe($values[$ci]) . '</w:t></w:r>';
        $inner = $mm[1];
        $pos = strrpos($inner, '</w:p>');
        if ($pos !== false) $inner = substr($inner, 0, $pos) . $run . substr($inner, $pos);
        else                $inner .= '<w:p><w:pPr><w:bidi/></w:pPr>' . $run . '</w:p>';
        return '<w:tc>' . $inner . '</w:tc>';
    }, $headerXml);
}

function buildQuestionsBody(): string {
    global $mcqHead,$mcqNote,$descHead,$descNote,$qWord,$optKeys,$optMap,$mcqs,$descs,$isEn;
    $body  = bpara($mcqHead, ['bold'=>true,'size'=>26,'rtl'=>!$isEn]);
    $body .= bpara($mcqNote, ['size'=>20,'rtl'=>!$isEn]);
    foreach ($mcqs as $n=>$m) {
        $body .= bpara(($n+1).'. '.$qWord.': '.($m['q'] ?? ''), ['rtl'=>!$isEn,'size'=>22]);
        $line=''; foreach ($optMap as $k=>$dk) $line .= $optKeys[$k].') '.($m[$dk] ?? '').'      ';
        $body .= bpara(trim($line), ['rtl'=>!$isEn,'size'=>20]);
    }
    $body .= bpara('', ['size'=>8]);
    $body .= bpara($descHead, ['bold'=>true,'size'=>26,'rtl'=>!$isEn]);
    $body .= bpara($descNote, ['size'=>20,'rtl'=>!$isEn]);
    foreach ($descs as $n=>$d) $body .= bpara(($n+1).'. '.$qWord.': '.($d['q'] ?? ''), ['rtl'=>!$isEn,'size'=>22]);
    return $body;
}

/** Extract the body content (without the trailing body-level sectPr) from a document.xml. */
function extractBody(string $docXml): string {
    if (!preg_match('#<w:body>(.*)</w:body>#s', $docXml, $bm)) return '';
    $inner = $bm[1];
    $pos = strrpos($inner, '<w:sectPr');           // remove trailing body-level sectPr
    if ($pos !== false) {
        $end = strpos($inner, '</w:sectPr>', $pos);
        if ($end !== false) $inner = substr($inner, 0, $pos) . substr($inner, $end + strlen('</w:sectPr>'));
    }
    return $inner;
}

function streamDocx(string $tmp, string $fname): void {
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
}

function rawurlencode_path(string $p): string {
    return implode('/', array_map('rawurlencode', explode('/', $p)));
}

// ── Base document: uploaded file or official template ─────────────
if ($isUpload) {
    $basePath = UPLOAD_DIR . $p['file_path'];
} else {
    $tplName  = $isEn ? 'exam-format-english-subject.docx' : 'exam-format.docx';
    $basePath = __DIR__ . '/../../assets/' . $tplName;
    if (!is_file($basePath)) $basePath = __DIR__ . '/../../assets/exam-format.docx';
}
if (!is_file($basePath)) { http_response_code(500); die('Base document not found.'); }

$safeSubj = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $p['subject_name']);

// ════════════════════════════════════════════════════════════════
// MAILING — one paper per student
// ════════════════════════════════════════════════════════════════
if ($mailing) {
    $sStmt = $pdo->prepare(
        'SELECT u.name, s.father_name, s.roll_no
         FROM students s JOIN users u ON u.id = s.user_id
         WHERE s.department = ? COLLATE utf8mb4_general_ci
           AND s.semester   = ? COLLATE utf8mb4_general_ci
           AND s.shift      = ? COLLATE utf8mb4_general_ci
         ORDER BY s.roll_no, u.name'
    );
    $sStmt->execute([$p['department'], $p['semester'], $p['shift']]);
    $students = $sStmt->fetchAll();
    if (empty($students)) { http_response_code(404); die('No students found for this class.'); }

    $tmp = tempnam(sys_get_temp_dir(), 'exammail') . '.docx';
    if (!copy($basePath, $tmp)) { http_response_code(500); die('Could not prepare document.'); }
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { http_response_code(500); die('Could not open document.'); }

    $origHeader     = $zip->getFromName('word/header1.xml');
    $origHeaderRels = $zip->getFromName('word/_rels/header1.xml.rels');
    $contentTypes   = $zip->getFromName('[Content_Types].xml');
    $docRels        = $zip->getFromName('word/_rels/document.xml.rels');
    $origDoc        = $zip->getFromName('word/document.xml');

    if ($origHeader === false || $contentTypes === false || $docRels === false || $origDoc === false) {
        $zip->close(); @unlink($tmp);
        http_response_code(422);
        die('The exam file is missing the standard header/structure. Please use the official template.');
    }

    preg_match('#<w:document[^>]*>#', $origDoc, $rootM);
    $rootTag = $rootM[0];

    // The per-student "unit" body: generated questions (form) or the uploaded doc body
    $unitBody = $isUpload ? extractBody($origDoc) : buildQuestionsBody();

    $relAdds = ''; $ctAdds = ''; $body = ''; $lastSect = '';
    $startRid = 900; $n = count($students);

    foreach ($students as $idx => $stu) {
        $rid      = 'rId' . ($startRid + $idx);
        $partName = 'headerS' . $idx . '.xml';

        $vals = $commonValues;
        $vals[10] = (string)($idx + 1);          // حاضری نمبر
        $vals[16] = $stu['father_name'] ?? '';   // د پلار نوم
        $vals[17] = $stu['name'] ?? '';          // نوم

        $zip->addFromString('word/' . $partName, injectHeader($origHeader, $vals));
        if ($origHeaderRels !== false) $zip->addFromString('word/_rels/' . $partName . '.rels', $origHeaderRels);
        $relAdds .= '<Relationship Id="' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="' . $partName . '"/>';
        $ctAdds  .= '<Override PartName="/word/' . $partName . '" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>';

        $sect = '<w:headerReference w:type="default" r:id="' . $rid . '"/>'
              . '<w:pgSz w:w="11907" w:h="16839" w:code="9"/>'
              . '<w:pgMar w:top="360" w:right="477" w:bottom="630" w:left="540" w:header="389" w:footer="360" w:gutter="0"/>'
              . '<w:cols w:space="720"/><w:bidi/>';

        $body .= $unitBody;
        if ($idx < $n - 1) {
            $body .= '<w:p><w:pPr><w:sectPr>' . $sect . '<w:type w:val="nextPage"/></w:sectPr></w:pPr></w:p>';
        } else {
            $lastSect = '<w:sectPr>' . $sect . '</w:sectPr>';
        }
    }

    $contentTypes = str_replace('</Types>', $ctAdds . '</Types>', $contentTypes);
    $docRels      = str_replace('</Relationships>', $relAdds . '</Relationships>', $docRels);
    $newDoc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . $rootTag . '<w:body>' . $body . $lastSect . '</w:body></w:document>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    $zip->addFromString('word/document.xml', $newDoc);
    $zip->close();

    streamDocx($tmp, 'exam_' . $safeSubj . '_' . $p['exam_type'] . '_mailing_' . $n . 'students.docx');
}

// ════════════════════════════════════════════════════════════════
// BLANK (manual) — single paper, common header only
// ════════════════════════════════════════════════════════════════
$tmp = tempnam(sys_get_temp_dir(), 'exampaper') . '.docx';
if (!copy($basePath, $tmp)) { http_response_code(500); die('Could not prepare document.'); }
$zip = new ZipArchive();
if ($zip->open($tmp) !== true) { http_response_code(500); die('Could not open document.'); }

// Inject common header values into header1.xml (if the table exists)
$headerXml = $zip->getFromName('word/header1.xml');
if ($headerXml !== false) {
    $zip->addFromString('word/header1.xml', injectHeader($headerXml, $commonValues));
}

if (!$isUpload) {
    // Form source: replace body with generated questions
    $origDoc = $zip->getFromName('word/document.xml');
    preg_match('#<w:document[^>]*>#', $origDoc, $rootM);
    $rootTag = $rootM[0] ?? '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    preg_match('#<w:sectPr.*?</w:sectPr>#s', $origDoc, $sectM);
    $sectPr = $sectM[0] ?? '<w:sectPr><w:pgSz w:w="11907" w:h="16839"/></w:sectPr>';
    $newDoc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . $rootTag . '<w:body>' . buildQuestionsBody() . $sectPr . '</w:body></w:document>';
    $zip->addFromString('word/document.xml', $newDoc);
}
// Upload source: keep the teacher's body as-is (only header injected)

$zip->close();
streamDocx($tmp, 'exam_' . $safeSubj . '_' . $p['exam_type'] . '.docx');
