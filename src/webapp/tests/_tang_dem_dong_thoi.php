<?php
declare(strict_types=1);

// Tiến trình con của P02-T26 (tests/db_giay_phep_test.php) — KHÔNG phải tệp test.
//   php _tang_dem_dong_thoi.php <user_id> giu|thuong <Y-m-d>
// giu    : mở transaction, tăng đếm, in kết quả rồi GIỮ khoá tới khi đọc được 1 dòng từ STDIN, sau đó commit.
// thuong : tăng đếm trong transaction riêng của tangDem().
// Kế thừa BIGWIN_CONFIG_FILE của runner → chạy trên DB test.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../lib/bigwin/repo/ThueBaoRepo.php';

[, $uid, $cheDo, $ngay] = $argv;
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'); // dòng log lý do của choCli không lẫn vào kết quả
$repo = new ThueBaoRepo(TenantContext::choCli((int) $uid, 'test P02-T26 đếm đồng thời'));
$ngayHan = new DateTimeImmutable($ngay, new DateTimeZone(BW_TZ));
$giu = $cheDo === 'giu';
if ($giu) {
    db()->beginTransaction();
}
echo $repo->tangDem('so_tin_doi_soat', $ngayHan) ? "OK\n" : "TU_CHOI\n";
if ($giu) {
    fflush(STDOUT);
    fgets(STDIN); // giữ khoá dòng tới khi tiến trình cha cho commit
    db()->commit();
}
