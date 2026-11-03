<?php
declare(strict_types=1);

// P03 S1.6 — NgayLamViec (thuần): ngày làm việc mặc định theo N8 — trước 18:30 → hôm qua, từ 18:30 → hôm nay.

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/../lib/bigwin/NgayLamViec.php';

function bw_luc(string $s): DateTimeImmutable
{
    return new DateTimeImmutable($s, new DateTimeZone(BW_TZ));
}

function bw_mac_dinh(string $luc): string
{
    return NgayLamViec::macDinh(bw_luc($luc))->format('Y-m-d');
}

function test_p03_t01_truoc_18_30_la_hom_qua(): void
{
    assert_same('2026-11-02', bw_mac_dinh('2026-11-03 18:29:59'));
    assert_same('2026-11-02', bw_mac_dinh('2026-11-03 17:00:00'), 'M03: 17:00 → hôm qua');
}

function test_p03_t02_tu_18_30_la_hom_nay(): void
{
    assert_same('2026-11-03', bw_mac_dinh('2026-11-03 18:30:00'));
    assert_same('2026-11-03', bw_mac_dinh('2026-11-03 18:30:01'));
    assert_same('2026-11-03', bw_mac_dinh('2026-11-03 23:59:59'));
}

function test_p03_t03_sau_nua_dem_la_hom_qua_cua_ngay_moi(): void
{
    assert_same('2026-11-03', bw_mac_dinh('2026-11-04 00:05:00'));
    assert_same('2026-12-31', bw_mac_dinh('2027-01-01 00:05:00'), 'Qua năm');
    assert_same('2026-02-28', bw_mac_dinh('2026-03-01 08:00:00'), 'Qua tháng (năm không nhuận)');
}

function test_p03_s16_mui_gio_khac_van_tinh_theo_gio_viet_nam(): void
{
    $utc = new DateTimeImmutable('2026-11-03 11:30:00', new DateTimeZone('UTC')); // = 18:30 giờ VN
    assert_same('2026-11-03', NgayLamViec::macDinh($utc)->format('Y-m-d'));
    assert_same('00:00:00', NgayLamViec::macDinh($utc)->format('H:i:s'));
}

function test_p03_s16_la_mac_dinh_va_chuan_hoa_ngay_nhap(): void
{
    $luc = bw_luc('2026-11-03 19:00:00');
    assert_same(true, NgayLamViec::laMacDinh('2026-11-03', $luc));
    assert_same(false, NgayLamViec::laMacDinh('2026-11-02', $luc));
    assert_same('2026-11-02', NgayLamViec::chuanHoa('2026-11-02'));
    foreach (['', '2026-02-30', '03-11-2026', '2026-11-3', "2026-11-03\n", null, ['x']] as $sai) {
        assert_same(null, NgayLamViec::chuanHoa($sai), 'Ngày nhập sai phải bị bỏ: ' . json_encode($sai));
    }
}
