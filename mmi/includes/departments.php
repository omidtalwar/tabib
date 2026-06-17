<?php
/**
 * Shared department helper — single source of truth.
 *
 * DB canonical value stored everywhere: name_en (English, e.g. "Nursing")
 * Pashto name (name_ps) is display-only, never stored in foreign columns.
 *
 * Usage:
 *   require_once __DIR__ . '/departments.php';
 *   $depts = get_departments($pdo);   // full rows
 *   $names = dept_names_en($pdo);     // ['Nursing','Pharmacy',...]
 */

function get_departments(PDO $pdo): array {
    try {
        return $pdo->query(
            'SELECT name_en, name_ps, max_semesters, sort_order
             FROM departments ORDER BY sort_order, name_en'
        )->fetchAll();
    } catch (Exception $e) {
        return _dept_fallback();
    }
}

function dept_names_en(PDO $pdo): array {
    return array_column(get_departments($pdo), 'name_en');
}

/** Returns max_semesters for a given English department name. */
function dept_max_semesters(PDO $pdo, string $nameEn): int {
    foreach (get_departments($pdo) as $d) {
        if ($d['name_en'] === $nameEn) return (int)$d['max_semesters'];
    }
    return 4;
}

/** Returns Pashto name for a given English name (for display). */
function dept_name_ps(PDO $pdo, string $nameEn): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (get_departments($pdo) as $d) {
            $cache[$d['name_en']] = $d['name_ps'] ?: $d['name_en'];
        }
    }
    return $cache[$nameEn] ?? $nameEn;
}

/**
 * Department label for display — Pashto preferred, falls back to English.
 * Use everywhere a department is shown to the user.
 * Returns htmlspecialchars-safe text.
 */
function dept_label(PDO $pdo, ?string $nameEn): string {
    if (!$nameEn) return '—';
    return htmlspecialchars(dept_name_ps($pdo, $nameEn));
}

/**
 * Renders a <select> element populated from the departments table.
 * $selected = currently selected English name.
 * $onChange  = optional onchange JS string.
 */
function dept_select(PDO $pdo, string $name, string $selected = '', string $id = '', string $onChange = ''): string {
    $depts  = get_departments($pdo);
    $idAttr = $id ? " id=\"$id\"" : '';
    $onAttr = $onChange ? " onchange=\"$onChange\"" : '';
    $html   = "<select name=\"$name\"$idAttr$onAttr>";
    $html  .= '<option value="">— Select Department —</option>';
    foreach ($depts as $d) {
        $en  = htmlspecialchars($d['name_en']);
        $ps  = htmlspecialchars($d['name_ps'] ?? '');
        $sel = $selected === $d['name_en'] ? ' selected' : '';
        $max = (int)$d['max_semesters'];
        $html .= "<option value=\"$en\" data-max=\"$max\"$sel>$en" . ($ps ? " ($ps)" : '') . "</option>";
    }
    $html .= '</select>';
    return $html;
}

/**
 * Returns departments as a JS-safe JSON object:
 * { "Nursing": { ps: "نرسنګ", max: 6 }, ... }
 * Embed with: <script>var DEPTS = <?= dept_js_map($pdo) ?>;</script>
 */
function dept_js_map(PDO $pdo): string {
    $map = [];
    foreach (get_departments($pdo) as $d) {
        $map[$d['name_en']] = ['ps' => $d['name_ps'] ?? '', 'max' => (int)$d['max_semesters']];
    }
    return json_encode($map, JSON_UNESCAPED_UNICODE);
}

function _dept_fallback(): array {
    return [
        ['name_en' => 'Nursing',    'name_ps' => 'نرسنګ',     'max_semesters' => 6, 'sort_order' => 1],
        ['name_en' => 'Pharmacy',   'name_ps' => 'درملپوهنه',  'max_semesters' => 4, 'sort_order' => 2],
        ['name_en' => 'Protiz',     'name_ps' => 'پروتیز',     'max_semesters' => 4, 'sort_order' => 3],
        ['name_en' => 'Technology', 'name_ps' => 'ټیکنالوجي', 'max_semesters' => 4, 'sort_order' => 4],
    ];
}
