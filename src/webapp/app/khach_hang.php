<?php
declare(strict_types=1);

// Page "Khách hàng & Giá" — khung rỗng (P03 S1.6); nghiệp vụ ở P07 (S4.1–S4.2).

require __DIR__ . '/_layout.php';

$ctx = khung_bat_dau('khach_hang');
khung_ve($ctx, 'Khách hàng & Giá', static function (array $ctx): void {
    trang_rong($ctx, 'Khách hàng & Giá', 'Danh sách khách, hồ sơ và bảng giá kế thừa Hệ thống → Đại lý → Khách.', 'P07 (S4.1–S4.2)', [['ghi', 'Thêm khách', 'fa-solid fa-user-plus']]);
});
