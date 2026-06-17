<?php
/**
 * One-time teacher seed script.
 * Run from browser: http://localhost/mmicollection/database/seed_teachers.php
 * Delete this file after use.
 */
require_once __DIR__ . '/../config/db.php';

// Verify migration has been applied
try {
    $pdo->query('SELECT teacher_no FROM teachers LIMIT 0');
} catch (Exception $e) {
    die('<b style="color:red">Error:</b> Run <code>migrate_exam_schedule.sql</code> first to add the teacher_no column.');
}

$defaultPassword  = 'mmi1234';
$defaultQual      = 'لیسانس';
$passwordHash     = password_hash($defaultPassword, PASSWORD_DEFAULT);

$teachers = [
    [ 1,  'درانی'],
    [ 2,  'هارون'],
    [ 3,  'وقاص'],
    [ 4,  'صمدزی'],
    [ 5,  'مولوی عبدالله صافی'],
    [ 6,  'طاهری'],
    [ 7,  'راسخ'],
    [ 8,  'عظیمی'],
    [ 9,  'جهانی'],
    [10,  'مشفق الرحمن'],
    [11,  'صدیقی'],
    [12,  'مولوی محمد اکبر'],
    [13,  'سباوون'],
    [14,  'سامع'],
    [15,  'اکرام'],
    [16,  'عابد'],
    [17,  'وزیری'],
    [18,  'احسان'],
    [19,  'الماس'],
    [20,  'شیخ خادم'],
    [21,  'ثناالله'],
    [22,  'ولی زاده'],
    [23,  'مولوی مولاجان'],
    [24,  'مومند'],
    [25,  'شیرمل'],
    [26,  'انوری'],
    [27,  'سیرت'],
    [28,  'زبیراحمد'],
    [29,  'فیضی'],
    [30,  'حسنزی'],
    [31,  'خلیلی'],
    [32,  'سروری'],
    [33,  'سعیدی'],
    [34,  'عبدالواجد'],
    [35,  'ځلاند'],
    [36,  'عبیدالله'],
    [37,  'صمیم'],
    [38,  'ناصح'],
    [39,  'احساس'],
    [40,  'دانش'],
    [41,  'مولوی ابو صهیب'],
    [42,  'دانش (۲)'],
    [43,  'سادات'],
    [44,  'امید'],
    [45,  'زبیر عباد'],
];

$inserted = 0;
$skipped  = 0;
$errors   = [];

echo '<pre style="font-family:monospace;font-size:13px;line-height:1.7;">';
echo "MMI Teacher Seed — " . count($teachers) . " teachers\n";
echo str_repeat('─', 50) . "\n";

$pdo->beginTransaction();
try {
    foreach ($teachers as [$no, $name]) {
        $teacherNo = 'TCH-' . str_pad($no, 4, '0', STR_PAD_LEFT);
        $email     = 'teacher_' . str_pad($no, 4, '0', STR_PAD_LEFT) . '@mmi.local';

        // Skip if teacher_no already exists
        $check = $pdo->prepare('SELECT t.id FROM teachers t WHERE t.teacher_no = ?');
        $check->execute([$teacherNo]);
        if ($check->fetch()) {
            echo "  SKIP  $teacherNo  $name  (already exists)\n";
            $skipped++;
            continue;
        }

        try {
            $pdo->prepare(
                'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "teacher")'
            )->execute([$name, $email, $passwordHash]);
            $uid = $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO teachers (user_id, teacher_no, qualification) VALUES (?, ?, ?)'
            )->execute([$uid, $teacherNo, $defaultQual]);

            echo "  OK    $teacherNo  $name\n";
            $inserted++;
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'duplicate entry' : $e->getMessage();
            echo "  FAIL  $teacherNo  $name  → $msg\n";
            $errors[] = "$teacherNo $name: $msg";
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nFATAL: " . $e->getMessage() . "\n";
}

echo str_repeat('─', 50) . "\n";
echo "Inserted : $inserted\n";
echo "Skipped  : $skipped\n";
echo "Errors   : " . count($errors) . "\n";
echo "\n✓ Done. Login with Teacher ID (e.g. TCH-0001) + password: $defaultPassword\n";
echo '</pre>';
echo '<p style="color:#888;font-size:12px;">Delete <code>database/seed_teachers.php</code> after use.</p>';
