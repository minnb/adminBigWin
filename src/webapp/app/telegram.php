<?php
declare(strict_types=1);

// Page "Telegram" — khung rỗng (P03 S1.6); nghiệp vụ ở P11 (S7.3–S7.4).

require __DIR__ . '/_layout.php';

$ctx = khung_bat_dau('telegram');
khung_ve($ctx, 'Telegram', static function (array $ctx): void {
    trang_rong($ctx, 'Telegram', 'Đồng bộ tin nhắn từ các nhóm Telegram của khách, rà soát rồi ghi vào sổ.', 'P11 (S7.3–S7.4)', [['phan_tich', 'Đồng bộ Telegram', 'fa-solid fa-rotate']]);
});
