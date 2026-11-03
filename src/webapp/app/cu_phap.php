<?php
declare(strict_types=1);

// Page "Cú pháp cược" — khung rỗng (P03 S1.6); nghiệp vụ ở P09 (S5.1–S5.2).

require __DIR__ . '/_layout.php';

$ctx = khung_bat_dau('cu_phap');
khung_ve($ctx, 'Cú pháp cược', static function (array $ctx): void {
    trang_rong($ctx, 'Cú pháp cược', 'Định nghĩa dàn số và từ khoá nhận diện cú pháp cược.', 'P09 (S5.1–S5.2)', [['ghi', 'Lưu dàn', 'fa-solid fa-floppy-disk']]);
});
