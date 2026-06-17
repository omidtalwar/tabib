<?php
/**
 * Schedule seed script — reads database/schedule.json and:
 *   1. Inserts unique subjects into the subjects table
 *   2. Assigns teacher courses from the teacher_subjects section
 *
 * Save schedule.json to database/schedule.json first.
 * Run once from browser: http://localhost/mmicollection/database/seed_schedule.php
 * Delete this file after use.
 */
require_once __DIR__ . '/../config/db.php';

$jsonPath = __DIR__ . '/schedule.json';
if (!file_exists($jsonPath)) {
    die('<b style="color:red">Error:</b> Put <code>schedule.json</code> in the <code>database/</code> folder first.');
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!$data) {
    die('<b style="color:red">Error:</b> Could not parse schedule.json — invalid JSON.');
}

// ── Lookup tables ─────────────────────────────────────────────────
$deptMap = [
    'نرسنګ'    => 'Nursing',
    'فارمسي'   => 'Pharmacy',
    'تکنالوجي' => 'Technology',
    'پروتیز'   => 'Protiz',
];
$semMap = [
    1 => '1st Semester', 2 => '2nd Semester', 3 => '3rd Semester',
    4 => '4th Semester', 5 => '5th Semester', 6 => '6th Semester',
];
$shiftMap = [
    '6am' => '06:00 – 09:00',
    '9am' => '09:00 – 12:00',
    '1pm' => '01:00 – 04:00',
];
// Skip non-teacher markers (both ک and ګ spellings appear in JSON)
$skipTeachers = [
    '(شاکرد محوری)', 'شاکرد محوری',   // ک variant
    '(شاګرد محوری)', 'شاګرد محوری',   // ګ variant (actual JSON value)
];

// Map JSON teacher names → exact DB names (handles spelling differences)
$nameMap = [
    'دانش'      => 'دانش',        // force exact match via alias (Unicode safety)
    'اکرام الله' => 'اکرام الله',  // added below in fix step
    'عاید'      => 'عاید',        // added below in fix step
];

echo '<pre style="font-family:monospace;font-size:13px;line-height:1.7;">';
echo "MMI Schedule Seed\n";
echo str_repeat('─', 65) . "\n\n";

// ══════════════════════════════════════════════════════════════════
// STEP 0 — Add missing teachers not in original seed
// ══════════════════════════════════════════════════════════════════
echo "STEP 0 — Missing teachers\n";

$missingTeachers = [
    ['name' => 'اکرام الله', 'no' => 'TCH-0046'],
    ['name' => 'عاید',       'no' => 'TCH-0047'],
];
$defaultPassword = password_hash('mmi1234', PASSWORD_DEFAULT);
$defaultQual     = 'لیسانس';

foreach ($missingTeachers as $mt) {
    // Check if already exists
    $chk = $pdo->prepare('SELECT u.id FROM users u WHERE u.name=? AND u.role="teacher"');
    $chk->execute([$mt['name']]);
    if ($chk->fetchColumn()) {
        echo "  SKIP  {$mt['no']}  {$mt['name']}  (already exists)\n";
        continue;
    }
    $email = 'teacher_' . str_replace('-','', strtolower($mt['no'])) . '@mmi.local';
    $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,"teacher")')
        ->execute([$mt['name'], $email, $defaultPassword]);
    $uid = $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO teachers (user_id, teacher_no, qualification) VALUES (?,?,?)')
        ->execute([$uid, $mt['no'], $defaultQual]);
    echo "  OK    {$mt['no']}  {$mt['name']}\n";
}
echo "\n";

// ══════════════════════════════════════════════════════════════════
// STEP 1 — Subjects
// Insert unique (name + department) combos into subjects table
// ══════════════════════════════════════════════════════════════════
echo "STEP 1 — Subjects\n";

$subSeen     = [];
$subInserted = 0;
$subSkipped  = 0;

foreach ($data['schedule'] as $entry) {
    $name = trim($entry['subject'] ?? '');
    if (!$name) continue;

    $dept  = $deptMap[$entry['department'] ?? ''] ?? ($entry['department'] ?? 'General');
    $sem   = $semMap[$entry['semester_number'] ?? 0] ?? ($entry['semester'] ?? '1st Semester');
    $key   = $name . '||' . $dept;

    if (isset($subSeen[$key])) continue;
    $subSeen[$key] = true;

    // Skip if already in table
    $chk = $pdo->prepare('SELECT id FROM subjects WHERE name = ? AND department = ?');
    $chk->execute([$name, $dept]);
    if ($chk->fetchColumn()) { $subSkipped++; continue; }

    $pdo->prepare(
        'INSERT INTO subjects (name, department, semester, credits) VALUES (?, ?, ?, 1)'
    )->execute([$name, $dept, $sem]);
    echo "  + " . str_pad($dept, 12) . " | $name\n";
    $subInserted++;
}

echo "\n  Inserted : $subInserted\n";
echo "  Skipped  : $subSkipped (already exist)\n\n";

// ══════════════════════════════════════════════════════════════════
// STEP 2 — Teacher courses
// Assign subjects to teachers from teacher_subjects map
// Deduplicates on teacher + subject + dept + semester + shift
// ══════════════════════════════════════════════════════════════════
echo "STEP 2 — Teacher Courses\n";

$tcInserted  = 0;
$tcSkipped   = 0;
$notFound    = [];

foreach ($data['teacher_subjects'] as $teacherName => $assignments) {
    if (in_array($teacherName, $skipTeachers)) continue;

    // Resolve alias (handles Unicode/spelling variants)
    $lookupName = $nameMap[$teacherName] ?? $teacherName;

    // Look up teacher — COLLATE bin ensures byte-exact Unicode match
    $tStmt = $pdo->prepare(
        'SELECT t.id FROM teachers t
         JOIN users u ON u.id = t.user_id
         WHERE u.name COLLATE utf8mb4_bin = ? AND u.role = "teacher"
         LIMIT 1'
    );
    $tStmt->execute([$lookupName]);
    $tRow = $tStmt->fetch();

    if (!$tRow) {
        $notFound[] = "$teacherName (looked up as: $lookupName)";
        echo "  ? NOT FOUND : $teacherName\n";
        continue;
    }
    $teacherId = $tRow['id'];

    // Deduplicate assignments for this teacher
    $seen      = [];
    $addedHere = 0;

    // Get current max no for this teacher
    $maxNo = (int)$pdo->prepare(
        'SELECT COALESCE(MAX(no), 0) FROM teacher_courses WHERE teacher_id = ?'
    )->execute([$teacherId]) ? $pdo->query(
        "SELECT COALESCE(MAX(no),0) FROM teacher_courses WHERE teacher_id = $teacherId"
    )->fetchColumn() : 0;
    $no = $maxNo + 1;

    foreach ($assignments as $a) {
        $subj  = trim($a['subject'] ?? '');
        if (!$subj) continue;

        $dept  = $deptMap[$a['department'] ?? ''] ?? ($a['department'] ?? '');
        $sem   = $semMap[$a['semester_number'] ?? 0] ?? ($a['semester'] ?? '');
        $shift = $shiftMap[$a['shift'] ?? ''] ?? ($a['shift'] ?? '');
        $key   = "$subj||$dept||$sem||$shift";

        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        // Skip if already assigned
        $chk = $pdo->prepare(
            'SELECT id FROM teacher_courses
             WHERE teacher_id=? AND subject_name=? AND department=? AND semester=? AND shift=?'
        );
        $chk->execute([$teacherId, $subj, $dept, $sem, $shift]);
        if ($chk->fetchColumn()) { $tcSkipped++; continue; }

        $pdo->prepare(
            'INSERT INTO teacher_courses
             (teacher_id, no, subject_name, department, semester, shift, credits)
             VALUES (?,?,?,?,?,?,1)'
        )->execute([$teacherId, $no, $subj, $dept, $sem, $shift]);
        $no++;
        $addedHere++;
        $tcInserted++;
    }

    echo "  ✓ " . str_pad($teacherName, 28) . " → $addedHere course(s) added\n";
}

echo "\n";
echo str_repeat('─', 65) . "\n";
echo "Teacher courses — Inserted : $tcInserted\n";
echo "Teacher courses — Skipped  : $tcSkipped (already exist)\n";
echo "Teachers not found         : " . count($notFound) . "\n";
if ($notFound) {
    echo "  Not found list: " . implode(', ', $notFound) . "\n";
}
echo "\n✓ Done! Delete database/seed_schedule.php after use.\n";
echo '</pre>';
