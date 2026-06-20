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

function cleanXmlT(string $raw): string {
    $raw = preg_replace('/\s+xmlns(?::\w[\w.-]*)?="[^"]*"/u', '', $raw);
    $raw = preg_replace('/\s+[\w][\w.-]*:[\w][\w.-]*="[^"]*"/u', '', $raw);
    $raw = preg_replace('/<(\\/?)[\w][\w.-]*:([\w][\w.-]*)/', '<$1$2', $raw);
    return $raw;
}

function parseXmlT(string $raw): ?SimpleXMLElement {
    try { return @new SimpleXMLElement(cleanXmlT($raw)); }
    catch (Exception $e) { return null; }
}

function colToIdxT(string $col): int {
    $col = strtoupper($col); $idx = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - 64);
    }
    return $idx - 1;
}

// Shared strings
$strings = [];
$ssRaw = $zip->getFromName('xl/sharedStrings.xml');
if ($ssRaw) {
    $ss = parseXmlT($ssRaw);
    if ($ss) {
        foreach ($ss->si as $si) {
            if (count($si->r) > 0) {
                $text = '';
                foreach ($si->r as $r) { if (isset($r->t)) $text .= (string)$r->t; }
                $strings[] = $text;
            } elseif (isset($si->t)) {
                $strings[] = (string)$si->t;
            } else {
                $strings[] = '';
            }
        }
    }
}

$skipWords = ['نوم','پیژندنه','ګڼه','نمبر','name','qualification','department','teacher','no','#'];

function shouldSkipT(string $val, array $sw): bool {
    $val = trim($val);
    if ($val === '' || mb_strlen($val, 'UTF-8') < 2) return true;
    $low = mb_strtolower($val, 'UTF-8');
    foreach ($sw as $w) {
        if (mb_strpos($low, mb_strtolower($w, 'UTF-8'), 0, 'UTF-8') !== false) return true;
    }
    return false;
}

$teachers = [];
$seen     = [];

for ($n = 1; $n <= 10; $n++) {
    $sheetRaw = $zip->getFromName("xl/worksheets/sheet{$n}.xml");
    if ($sheetRaw === false) break;

    $sheet = parseXmlT($sheetRaw);
    if (!$sheet || !isset($sheet->sheetData)) continue;

    foreach ($sheet->sheetData->row as $row) {
        $rowNum = (int)($row['r'] ?? 0);
        if ($rowNum < 2) continue; // skip header row

        $cols = [];
        foreach ($row->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            if (!preg_match('/^([A-Z]+)(\d+)$/i', $ref, $m)) continue;
            $colIdx = colToIdxT($m[1]);
            $type   = (string)($cell['t'] ?? '');
            $rawVal = (string)($cell->v ?? '');
            $val    = ($type === 's' && $rawVal !== '') ? ($strings[(int)$rawVal] ?? '') : $rawVal;
            $cols[$colIdx] = trim($val);
        }

        // Col A (0) = name, Col B (1) = qualification, Col C (2) = department
        $name  = $cols[0] ?? '';
        $qual  = $cols[1] ?? '';
        $dept  = $cols[2] ?? '';

        if (!$name || shouldSkipT($name, $skipWords)) continue;

        $key = $name;
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $teachers[] = ['name' => $name, 'qualification' => $qual, 'department' => $dept];
        }
    }
}

$zip->close();
libxml_clear_errors();

jsonOut(['teachers' => $teachers, 'count' => count($teachers)]);
