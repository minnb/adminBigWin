<?php
declare(strict_types=1);

// Page "Kết quả XSMB" — khung rỗng (P03 S1.6); nghiệp vụ ở P06 (S3.3).

require __DIR__ . '/_layout.php';

$ctx = khung_bat_dau('kqxs');
khung_ve($ctx, 'Kết quả XSMB', static function (array $ctx): void {
    trang_rong($ctx, 'Kết quả XSMB', 'Kết quả xổ số miền Bắc theo ngày làm việc, bảng lô đầu – đuôi.', 'P06 (S3.3)', [['ghi', 'Lấy kết quả', 'fa-solid fa-cloud-arrow-down']]);
});
