<?php
declare(strict_types=1);

// Hàm cấu hình test (P01 S1.0) — tệp hàm thuần, nạp bằng require_once từ _cau_hinh.php, _db.php, _router.php.
// Cần lib/bootstrap.php đã nạp (LoiCauHinh).

/** DSN → [DSN máy chủ (bỏ dbname), tên DB]. Nguồn DUY NHẤT để đọc dbname trong DSN test. */
function bw_tach_dsn_test(string $dsn): array
{
    if (!preg_match('/(?<=^|[:;])dbname=([^;]*);?/', $dsn, $m, PREG_OFFSET_CAPTURE)) {
        throw new LoiCauHinh('db_test_sai', 'db_test_dsn thiếu "dbname=…".');
    }
    return [substr($dsn, 0, $m[0][1]) . substr($dsn, $m[0][1] + strlen($m[0][0])), $m[1][0]];
}

/** Tên DB phải kết thúc "_test" — mọi DROP/CREATE của harness không thể trúng DB thật. */
function bw_kiem_ten_db_test(string $ten): string
{
    if (!preg_match('/^[A-Za-z0-9_]+_test$/', $ten)) {
        throw new LoiCauHinh('db_test_sai', "Tên DB test phải kết thúc \"_test\" (đang là \"$ten\") — chặn xoá nhầm DB thật.");
    }
    return $ten;
}

function bw_ten_db_test(string $dsn): string
{
    return bw_kiem_ten_db_test(bw_tach_dsn_test($dsn)[1]);
}

/** Cấu hình thật → cấu hình test (db_* = db_test_*, moi_truong = test). */
function bw_cau_hinh_test(array $goc): array
{
    foreach (['db_test_dsn', 'db_test_user', 'db_test_pass'] as $k) {
        if (!array_key_exists($k, $goc)) {
            throw new LoiCauHinh('thieu_db_test', "Thiếu khoá '$k' trong config.php — DB test chưa cấu hình (xem config.sample.php).");
        }
    }
    bw_ten_db_test((string) $goc['db_test_dsn']);
    return ['db_dsn' => $goc['db_test_dsn'], 'db_user' => $goc['db_test_user'], 'db_pass' => $goc['db_test_pass'], 'moi_truong' => 'test'] + $goc;
}
