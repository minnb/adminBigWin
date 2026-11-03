<?php
declare(strict_types=1);

// P02 S1.5 — GiayPhepGuard (thuần, không DB): trạng thái thuê bao tính từ ngày + ma trận quyền §1.4.

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/../lib/bigwin/GiayPhepGuard.php';

/** Bản ghi thuê bao (thue_bao JOIN goi_dich_vu) tối thiểu cho guard. */
function bw_tb(string $hetHan, array $them = []): array
{
    return $them + ['het_han' => $hetHan, 'tam_khoa' => 0, 'goi_ma' => 'unlimited', 'so_ngay_an_han' => 3, 'so_tin_ngay_toi_da' => null];
}

function bw_ngay(string $ymd): DateTimeImmutable
{
    return new DateTimeImmutable($ymd, new DateTimeZone(BW_TZ));
}

/** [trạng thái, doc, ghi, phan_tich, cảnh báo] tại ngày $homNay. */
function bw_ma_tran(string $vaiTro, ?array $tb, string $homNay): array
{
    $kq = [GiayPhepGuard::trangThai($vaiTro, $tb, bw_ngay($homNay))];
    foreach (GiayPhepGuard::CHE_DO as $cheDo) {
        $kq[] = GiayPhepGuard::kiemTra($vaiTro, $tb, $cheDo, bw_ngay($homNay))['cho_phep'];
    }
    $kq[] = GiayPhepGuard::kiemTra($vaiTro, $tb, 'doc', bw_ngay($homNay))['canh_bao'];
    return $kq;
}

function test_p02_t20_het_han_dung_hom_nay_van_hieu_luc(): void
{
    assert_same(['hieu_luc', true, true, true, false], bw_ma_tran('user', bw_tb('2026-11-03'), '2026-11-03'));
    assert_same(['hieu_luc', true, true, true, false], bw_ma_tran('user', bw_tb('2027-01-01'), '2026-11-03'));
}

function test_p02_t21_qua_han_trong_an_han_van_qua_kem_canh_bao(): void
{
    assert_same(['an_han', true, true, true, true], bw_ma_tran('user', bw_tb('2026-11-02'), '2026-11-03'));
    assert_same(['an_han', true, true, true, true], bw_ma_tran('user', bw_tb('2026-10-31'), '2026-11-03'), 'Ngày cuối ân hạn (hết hạn + 3) vẫn ân hạn');
    assert_same(['het_han', true, false, false, false], bw_ma_tran('user', bw_tb('2026-10-30'), '2026-11-03'), 'Qua ngày cuối ân hạn → hết hạn');
    assert_same(['het_han', true, false, false, false], bw_ma_tran('user', bw_tb('2026-11-02', ['so_ngay_an_han' => 0]), '2026-11-03'), 'Gói không ân hạn');
}

function test_p02_t22_qua_an_han_chi_con_doc(): void
{
    assert_same(['het_han', true, false, false, false], bw_ma_tran('user', bw_tb('2026-10-30'), '2026-11-03'));
    $kq = GiayPhepGuard::kiemTra('user', bw_tb('2026-10-30'), 'ghi', bw_ngay('2026-11-03'));
    assert_true(str_contains($kq['thong_diep'], 'hết hạn'), 'Thông điệp phải nói rõ hết hạn: ' . $kq['thong_diep']);
}

function test_p02_t23_tam_khoa_chan_ca_ba_che_do_du_con_han(): void
{
    assert_same(['tam_khoa', false, false, false, false], bw_ma_tran('user', bw_tb('2027-01-01', ['tam_khoa' => 1]), '2026-11-03'));
    assert_same(['tam_khoa', false, false, false, false], bw_ma_tran('user', bw_tb('2026-01-01', ['tam_khoa' => 1]), '2026-11-03'), 'Tạm khoá ưu tiên hơn hết hạn');
}

function test_p02_t24_admin_khong_can_thue_bao(): void
{
    assert_same(['hieu_luc', true, true, true, false], bw_ma_tran('admin', null, '2026-11-03'));
    assert_same(['hieu_luc', true, true, true, false], bw_ma_tran('admin', bw_tb('2020-01-01', ['tam_khoa' => 1]), '2026-11-03'), 'Admin dùng gói nội bộ, bỏ qua dòng thuê bao');
    assert_same(GiayPhepGuard::GOI_NOI_BO_ADMIN, GiayPhepGuard::hanMuc('admin', null));
    assert_same(null, GiayPhepGuard::hanMuc('admin', null)['so_tin_ngay_toi_da'], 'Gói nội bộ không giới hạn');
}

function test_p02_s15_dung_thu_va_chua_co_thue_bao(): void
{
    assert_same(['dung_thu', true, true, true, false], bw_ma_tran('user', bw_tb('2026-11-10', ['goi_ma' => 'dung_thu']), '2026-11-03'));
    assert_same(['an_han', true, true, true, true], bw_ma_tran('user', bw_tb('2026-11-01', ['goi_ma' => 'dung_thu']), '2026-11-03'));
    assert_same(['chua_co_thue_bao', false, false, false, false], bw_ma_tran('user', null, '2026-11-03'), 'Q-P02-2: chưa có thuê bao → chặn hết (fail-closed)');
    assert_same(0, GiayPhepGuard::hanMuc('user', null)['so_tin_ngay_toi_da'], 'Chưa có thuê bao → hạn mức 0');
    assert_same(3, GiayPhepGuard::hanMuc('user', bw_tb('2027-01-01', ['so_tin_ngay_toi_da' => 3]))['so_tin_ngay_toi_da']);
}

function test_p03_s16_so_ngay_con_cho_bang_thue_bao(): void
{
    $kq = static fn (string $vaiTro, ?array $tb): array => array_intersect_key(GiayPhepGuard::kiemTra($vaiTro, $tb, 'doc', bw_ngay('2026-11-03')), ['het_han' => 1, 'so_ngay_con' => 1]);
    assert_same(['het_han' => '2026-11-08', 'so_ngay_con' => 5], $kq('user', bw_tb('2026-11-08')));
    assert_same(['het_han' => '2026-11-03', 'so_ngay_con' => 0], $kq('user', bw_tb('2026-11-03')));
    assert_same(['het_han' => '2026-11-01', 'so_ngay_con' => -2], $kq('user', bw_tb('2026-11-01')));
    assert_same(['het_han' => null, 'so_ngay_con' => null], $kq('user', null));
    assert_same(['het_han' => null, 'so_ngay_con' => null], $kq('admin', bw_tb('2026-11-08')));
}

function test_p02_s15_che_do_hoac_trang_thai_la_bi_tu_choi(): void
{
    assert_true(bw_test_bat_loi(static fn () => GiayPhepGuard::kiemTra('user', bw_tb('2027-01-01'), 'xoa', bw_ngay('2026-11-03'))) !== null, 'Chế độ lạ phải lỗi, không mặc định cho qua');
    assert_true(bw_test_bat_loi(static fn () => GiayPhepGuard::choPhep('vinh_vien', 'doc')) !== null);
    assert_true(bw_test_bat_loi(static fn () => GiayPhepGuard::trangThai('khach', null, bw_ngay('2026-11-03'))) !== null, 'Vai trò lạ phải lỗi');
}

function test_p02_t30_gioi_han_dai_ly_nen_tang(): void
{
    assert_same(false, GiayPhepGuard::coTheThemDaiLy(2, 2));
    assert_same(false, GiayPhepGuard::coTheThemDaiLy(2, 3));
    assert_same(true, GiayPhepGuard::coTheThemDaiLy(2, 1));
    assert_same(true, GiayPhepGuard::coTheThemDaiLy(null, 10_000), 'NULL = không giới hạn');
    assert_same(false, GiayPhepGuard::coTheThemDaiLy(0, 0));
}

/** GiayPhepGuard::homNay() trong tiến trình con với cấu hình $cfg (stdout + stderr). */
function bw_gp_hom_nay_voi(array $cfg): string
{
    $tep = tempnam(sys_get_temp_dir(), 'bwcfg') . '.php';
    file_put_contents($tep, '<?php return ' . var_export($cfg, true) . ';');
    try {
        [, $ra, $loi] = bw_test_chay([PHP_BINARY, '-r', 'require ' . var_export(BW_WEBAPP_DIR . '/lib/bigwin/GiayPhepGuard.php', true)
            . '; echo GiayPhepGuard::homNay()->format("Y-m-d");'], ['BIGWIN_CONFIG_FILE' => $tep]);
        return trim($ra . $loi);
    } finally {
        @unlink($tep);
    }
}

function test_p02_s15_ngay_gia_lap_chi_co_hieu_luc_o_moi_truong_test(): void
{
    assert_same('2030-01-02', bw_gp_hom_nay_voi(['moi_truong' => 'test', 'ngay_gia_lap' => '2030-01-02']));
    assert_same(today_vn()->format('Y-m-d'), bw_gp_hom_nay_voi(['moi_truong' => 'production', 'ngay_gia_lap' => '2030-01-02']), 'Production bỏ qua ngày giả lập');
    assert_same(today_vn()->format('Y-m-d'), bw_gp_hom_nay_voi(['ngay_gia_lap' => '2030-01-02']), 'Thiếu moi_truong = không phải test');
    assert_true(str_contains(bw_gp_hom_nay_voi(['moi_truong' => 'test', 'ngay_gia_lap' => '2030-02-31']), 'ngay_gia_lap'), 'Ngày giả lập sai phải báo lỗi cấu hình');
}
