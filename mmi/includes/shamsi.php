<?php
function toShamsi(int $gy, int $gm, int $gd): array {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    if ($gy > 1600) { $jy = 979; $gy -= 1600; } else { $jy = 0; $gy -= 621; }
    $gy2  = $gm > 2 ? $gy + 1 : $gy;
    $days = 365*$gy + intdiv($gy2+3,4) - intdiv($gy2+99,100) + intdiv($gy2+399,400)
            - 80 + $gd + $g_d_m[$gm - 1];
    $jy  += 33 * intdiv($days, 12053); $days %= 12053;
    $jy  +=  4 * intdiv($days,  1461); $days %= 1461;
    if ($days > 365) { $jy += intdiv($days-1, 365); $days = ($days-1) % 365; }
    $jm = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
    $jd = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);
    return ['y' => $jy, 'm' => $jm, 'd' => $jd];
}

const SHAMSI_MONTHS = ['حمل','ثور','جوزا','سرطان','اسد','سنبله','میزان','عقرب','قوس','جدی','دلو','حوت'];

// Returns e.g. "20 ثور 1404"
function shamsiDate(?string $gregDatetime = null): string {
    date_default_timezone_set('Asia/Kabul');
    if ($gregDatetime) {
        $ts = strtotime($gregDatetime);
        $s  = toShamsi((int)date('Y',$ts), (int)date('n',$ts), (int)date('j',$ts));
    } else {
        $s = toShamsi((int)date('Y'), (int)date('n'), (int)date('j'));
    }
    return $s['d'] . ' ' . SHAMSI_MONTHS[$s['m']-1] . ' ' . $s['y'];
}

// Returns numeric "1404/02/20"
function shamsiDateNumeric(?string $gregDatetime = null): string {
    date_default_timezone_set('Asia/Kabul');
    if ($gregDatetime) {
        $ts = strtotime($gregDatetime);
        $s  = toShamsi((int)date('Y',$ts), (int)date('n',$ts), (int)date('j',$ts));
    } else {
        $s = toShamsi((int)date('Y'), (int)date('n'), (int)date('j'));
    }
    return $s['y'] . '/' . str_pad($s['m'],2,'0',STR_PAD_LEFT) . '/' . str_pad($s['d'],2,'0',STR_PAD_LEFT);
}
