<?php
declare(strict_types=1);

// Page "Lịch sử & Chốt sổ" — khung rỗng (P03 S1.6); nghiệp vụ ở P10 (S6.1–S6.3).

require __DIR__ . '/_layout.php';

$ctx = khung_bat_dau('lich_su');
khung_ve($ctx, 'Lịch sử & Chốt sổ', static function (array $ctx): void {
    trang_rong($ctx, 'Lịch sử & Chốt sổ', 'Lịch sử đối soát, chốt sổ theo khách và in báo cáo.', 'P10 (S6.1–S6.3)', [['doc', 'In phiếu', 'fa-solid fa-print'], ['ghi', 'Sửa phiên', 'fa-solid fa-pen']]);
});
