<?php
ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

function jsonOut(array $data): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['xlsx']['tmp_name'])) {
    jsonOut(['error' => 'No file uploaded.']);
}

$file = $_FILES['xlsx'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonOut(['error' => 'Upload error (code ' . $file['error'] . ').']);
}
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
    jsonOut(['error' => 'Only .xlsx files are supported.']);
}

$zip = new ZipArchive();
if ($zip->open($file['tmp_name']) !== true) {
    jsonOut(['error' => 'Cannot open Excel file — may be corrupted or wrong format.']);
}

libxml_use_internal_errors(true);

/* ── Strip ALL namespace cruft from XML so SimpleXML works ── */
function cleanXml(string $raw): string {
    // 1. Remove xmlns declarations: xmlns="..." and xmlns:prefix="..."
    $raw = preg_replace('/\s+xmlns(?::\w[\w.-]*)?="[^"]*"/u', '', $raw);
    // 2. Remove namespace-prefixed attributes: mc:Ignorable="..." xr:uid="..."
    $raw = preg_replace('/\s+[\w][\w.-]*:[\w][\w.-]*="[^"]*"/u', '', $raw);
    // 3. Remove namespace prefix from element names: <mc:X> → <X>, </mc:X> → </X>
    $raw = preg_replace('/<(\\/?)[\w][\w.-]*:([\w][\w.-]*)/', '<$1$2', $raw);
    return $raw;
}

function parseXml(string $raw): ?SimpleXMLElement {
    $clean = cleanXml($raw);
    try {
        $el = @new SimpleXMLElement($clean);
        return $el;
    } catch (Exception $e) {
        return null;
    }
}

/* ── Shared strings ─────────────────────────────────────── */
$strings   = [];
$debugInfo = ['strings_count' => 0, 'sheets_found' => 0, 'sheets_parsed' => 0, 'cells_read' => []];

$ssRaw = $zip->getFromName('xl/sharedStrings.xml');
if ($ssRaw) {
    $ss = parseXml($ssRaw);
    if ($ss) {
        foreach ($ss->si as $si) {
            if (count($si->r) > 0) {
                $text = '';
                foreach ($si->r as $r) {
                    if (isset($r->t)) $text .= (string)$r->t;
                }
                $strings[] = $text;
            } elseif (isset($si->t)) {
                $strings[] = (string)$si->t;
            } else {
                $strings[] = '';
            }
        }
    }
}
$debugInfo['strings_count'] = count($strings);

/* ── Column letter → 0-based index ─────────────────────── */
function colToIdx(string $col): int {
    $col = strtoupper($col);
    $idx = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - 64);
    }
    return $idx - 1;
}

/* ── Values that mark a header cell, not a student name ─── */
$skipWords = ['نوم','پلار','پیژندنه','ګڼه','کتني','نمبر','لومړی','دوهم',
              'سمستر','ډیپارتمنت','name','father','no','roll','sheet','#'];

function shouldSkip(string $val, array $sw): bool {
    $val = trim($val);
    if ($val === '') return true;
    if (is_numeric($val)) return true;
    if (mb_strlen($val, 'UTF-8') < 2) return true;
    $low = mb_strtolower($val, 'UTF-8');
    foreach ($sw as $w) {
        if (mb_strpos($low, mb_strtolower($w, 'UTF-8'), 0, 'UTF-8') !== false) return true;
    }
    return false;
}

/* ── Iterate sheets ─────────────────────────────────────── */
$students = [];
$seen     = [];

for ($n = 1; $n <= 30; $n++) {
    $sheetRaw = $zip->getFromName("xl/worksheets/sheet{$n}.xml");
    if ($sheetRaw === false) break;

    $debugInfo['sheets_found']++;
    $sheet = parseXml($sheetRaw);
    if (!$sheet || !isset($sheet->sheetData)) continue;

    $debugInfo['sheets_parsed']++;
    $rowData = [];

    foreach ($sheet->sheetData->row as $row) {
        $rowNum = (int)($row['r'] ?? 0);
        if ($rowNum < 8 || $rowNum > 65) continue;

        foreach ($row->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            if (!preg_match('/^([A-Z]+)(\d+)$/i', $ref, $m)) continue;

            $colIdx = colToIdx($m[1]);
            $type   = (string)($cell['t'] ?? '');
            $rawVal = (string)($cell->v ?? '');

            $val = ($type === 's' && $rawVal !== '') ? ($strings[(int)$rawVal] ?? '') : $rawVal;
            $val = trim($val);

            $rowData[$rowNum][$colIdx] = $val;

            // Collect first 40 non-empty cells for debug
            if ($n === 1 && count($debugInfo['cells_read']) < 40 && $val !== '') {
                $debugInfo['cells_read'][] = "row$rowNum col$colIdx({$m[1]}): $val";
            }
        }
    }

    foreach ($rowData as $cols) {
        // Left list: I(8)=name H(7)=father
        $name = $cols[8] ?? '';
        $dad  = $cols[7] ?? '';
        if ($name && !shouldSkip($name, $skipWords)) {
            $key = $name . '||' . $dad;
            if (!isset($seen[$key])) { $seen[$key] = true; $students[] = ['name' => $name, 'father_name' => $dad]; }
        }
        // Right list: Q(16)=name P(15)=father
        $name = $cols[16] ?? '';
        $dad  = $cols[15] ?? '';
        if ($name && !shouldSkip($name, $skipWords)) {
            $key = $name . '||' . $dad;
            if (!isset($seen[$key])) { $seen[$key] = true; $students[] = ['name' => $name, 'father_name' => $dad]; }
        }
    }
}

$zip->close();
libxml_clear_errors();

// Return debug info alongside results so we can diagnose if count is 0
jsonOut(['students' => $students, 'count' => count($students), '_debug' => $debugInfo]);
