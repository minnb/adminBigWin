<?php
declare(strict_types=1);

// Page "Nhập tin nhanh" — khung rỗng (P03 S1.6); nghiệp vụ ở P08 (S4.4).

require __DIR__ . '/_layout.php';

$ctx = khung_bat_dau('nhap_tin');
khung_ve($ctx, 'Nhập tin nhanh', static function (array $ctx): void {
    trang_rong($ctx, 'Nhập tin nhanh', 'Dán tin nhắn của khách, kiểm tra cú pháp và đối soát ngay theo bảng giá của khách.', 'P08 (S4.4)', [['phan_tich', 'Đối soát & Lưu', 'fa-solid fa-floppy-disk']]);
});
