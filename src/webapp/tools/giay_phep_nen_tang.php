<?php
declare(strict_types=1);

// Giấy phép nền tảng — số đại lý (role=user) đang hoạt động tối đa của bản cài đặt (P03 QĐ7: chỉ sửa ở đây, không có UI).
//   php tools/giay_phep_nen_tang.php                         → in hiện trạng "Đã dùng X/Y"
//   php tools/giay_phep_nen_tang.php <số|khong-gioi-han> [ghi chú]

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/bigwin/repo/GiayPhepNenTangRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/ThueBaoRepo.php';

$ctx = TenantContext::heThong('CLI giay_phep_nen_tang');
$giayPhep = new GiayPhepNenTangRepo($ctx);
$giaTri = $argv[1] ?? null;
if ($giaTri !== null) {
    if ($giaTri !== 'khong-gioi-han' && preg_match('/^\d{1,9}$/', $giaTri) !== 1) {
        fwrite(STDERR, "Dung: php tools/giay_phep_nen_tang.php [<so >= 0>|khong-gioi-han] [ghi chu]\n");
        exit(1);
    }
    $giayPhep->datToiDa($giaTri === 'khong-gioi-han' ? null : (int) $giaTri, (string) ($argv[2] ?? ''));
}
$hien = $giayPhep->hienTai();
$dung = (new ThueBaoRepo($ctx))->soDaiLyHoatDong();
echo 'Giay phep nen tang: da dung ', $dung, '/', $hien['so_dai_ly_toi_da'] ?? 'khong gioi han', ' dai ly — ', $hien['ghi_chu'], "\n";
