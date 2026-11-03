<?php
declare(strict_types=1);

// Cron hằng ngày (P02 S1.5): ghi lịch sử chuyển trạng thái thuê bao (vào ân hạn / hết hạn …) và in danh sách sắp hết hạn
// để nhắc gia hạn. Trạng thái luôn TÍNH RA từ ngày lúc dùng — cron chỉ ghi dấu vết; chạy lại cùng ngày không ghi trùng.
//   php tools/cap_nhat_thue_bao.php
//   crontab: 5 0 * * * php /duong/dan/src/webapp/tools/cap_nhat_thue_bao.php >> /var/log/bigwin_thue_bao.log 2>&1

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../lib/bigwin/repo/ThueBaoRepo.php';

const NHAC_TRUOC_NGAY = 3;

$homNay = GiayPhepGuard::homNay();
$repo = new ThueBaoRepo(TenantContext::heThong('cron cap_nhat_thue_bao'));
$daGhi = $repo->ghiChuyenTrangThaiTuDong($homNay);
echo 'OK ', $homNay->format('Y-m-d'), ": ghi $daGhi chuyển trạng thái\n";
foreach ($repo->sapHetHan($homNay, NHAC_TRUOC_NGAY) as $d) {
    echo "NHẮC: đại lý #{$d['user_id']} ({$d['username']}) hết hạn {$d['het_han']}\n";
}
