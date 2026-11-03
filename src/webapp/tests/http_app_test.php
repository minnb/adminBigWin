<?php
declare(strict_types=1);

// P03 S1.6 — Khung app (app/_layout.php) qua HTTP thật: chặn đăng nhập, 6 page, sidebar theo quyền, đổi mật khẩu
// (modal → app/ajax/doi_mat_khau.php, độc lập giấy phép — QĐ5), băng thuê bao + ẩn nút theo chế độ (T11).

require_once __DIR__ . '/_thue_bao.php';

const BW_APP_TRANG = ['nhap_tin', 'telegram', 'kqxs', 'khach_hang', 'lich_su', 'cu_phap'];

/** Tạo tài khoản $ten ($them như bw_auth_tao_user) rồi đăng nhập jar cùng tên. */
function bw_app_vao(string $ten, array $them = []): int
{
    $uid = bw_auth_tao_user($ten, $them);
    bw_http_xoa_cookie($ten);
    bw_auth_dang_nhap($ten, $ten);
    return $uid;
}

function bw_app_trang(string $jar, string $trang, string $truyVan = ''): array
{
    return bw_http('GET', "/app/$trang.php$truyVan", ['jar' => $jar]);
}

/** POST app/ajax/doi_mat_khau.php: [mã HTTP, loi_truong]. */
function bw_app_doi_mk(string $jar, string $cu, string $moi, ?string $xacNhan = null): array
{
    $r = bw_http('POST', '/app/ajax/doi_mat_khau.php', ['jar' => $jar, 'csrf' => bw_auth_csrf($jar),
        'json' => ['mat_khau_cu' => $cu, 'mat_khau_moi' => $moi, 'xac_nhan' => $xacNhan ?? $moi]]);
    return [$r['ma'], $r['json']['loi_truong'] ?? null, $r];
}

function test_p03_t04_chua_dang_nhap_moi_page_ve_login(): void
{
    bw_http_xoa_cookie('t04');
    foreach ([...BW_APP_TRANG, 'index'] as $trang) {
        $r = bw_app_trang('t04', $trang);
        assert_same(302, $r['ma'], "$trang.php phải chuyển hướng");
        assert_true(str_ends_with($r['header']['location'] ?? '', 'login.php'), "$trang.php → login, nhận: " . ($r['header']['location'] ?? ''));
    }
}

function test_p03_t07_sau_page_co_csrf_va_sidebar_theo_quyen(): void
{
    bw_gp_lam_sach();
    bw_app_vao('daily07');
    foreach (BW_APP_TRANG as $trang) {
        $r = bw_app_trang('daily07', $trang);
        assert_same(200, $r['ma'], "$trang.php");
        assert_true(bw_auth_meta_csrf($r['than']) !== null, "$trang.php thiếu <meta name=\"csrf-token\">");
        foreach (BW_APP_TRANG as $muc) {
            assert_true(str_contains($r['than'], "data-trang=\"$muc\""), "$trang.php: sidebar thiếu mục $muc");
        }
        assert_true(str_contains($r['than'], "data-trang=\"$trang\" aria-current=\"page\""), "$trang.php: mục đang mở phải được đánh dấu");
        assert_true(!str_contains($r['than'], 'data-trang="quan_tri"'), "$trang.php: user thường không được thấy mục Quản trị");
    }
    bw_app_vao('admin07', ['role' => 'admin']);
    assert_true(str_contains(bw_app_trang('admin07', 'nhap_tin')['than'], 'data-trang="quan_tri"'), 'Admin thấy mục Quản trị');
}

function test_p03_s16_app_goc_chuyen_toi_nhap_tin_va_ngay_lam_viec(): void
{
    bw_gp_lam_sach();
    bw_app_vao('daily16');
    $r = bw_http('GET', '/app/', ['jar' => 'daily16']);
    assert_true($r['ma'] === 302 && str_ends_with($r['header']['location'] ?? '', 'nhap_tin.php'), 'app/ → nhap_tin.php');
    $macDinh = NgayLamViec::hienTai()->format('Y-m-d');
    $than = bw_app_trang('daily16', 'nhap_tin')['than'];
    assert_true(str_contains($than, "value=\"$macDinh\"") && !str_contains($than, 'data-ngay-khac'), 'Mặc định: ngày làm việc N8, không tô màu');
    $than = bw_app_trang('daily16', 'nhap_tin', '?ngay=2020-01-02')['than'];
    assert_true(str_contains($than, 'value="2020-01-02"') && str_contains($than, 'data-ngay-khac'), 'Ngày khác mặc định → ô đổi màu + "Về hôm nay"');
    assert_true(str_contains($than, 'nhap_tin.php?ngay=2020-01-02') || str_contains($than, 'telegram.php?ngay=2020-01-02'), 'Chuyển page giữ ngày đang chọn');
    $than = bw_app_trang('daily16', 'nhap_tin', '?ngay=2020-02-31%22%3E%3Cb%3Ebw%3C%2Fb%3E')['than'];
    assert_true(str_contains($than, "value=\"$macDinh\"") && !str_contains($than, '2020-02-31') && !str_contains($than, '<b>bw</b>'),
        'Ngày sai → bỏ, không phản chiếu đầu vào');
}

function test_p03_t09_doi_mat_khau_bao_loi_tung_truong(): void
{
    bw_gp_lam_sach();
    $uid = bw_app_vao('daily09');
    [$ma, $loi] = bw_app_doi_mk('daily09', 'SaiCu#123456', 'MatKhauMoi#2026');
    assert_same([422, ['mat_khau_cu']], [$ma, array_keys($loi ?? [])], 'Sai mật khẩu cũ');
    [$ma, $loi] = bw_app_doi_mk('daily09', BW_MK, 'ngan123');
    assert_same([422, ['mat_khau_moi']], [$ma, array_keys($loi ?? [])], 'Mật khẩu mới < 10 ký tự');
    assert_true(str_contains($loi['mat_khau_moi'], '10'), 'Thông điệp nêu độ dài tối thiểu');
    [$ma, $loi] = bw_app_doi_mk('daily09', BW_MK, 'MatKhauMoi#2026', 'MatKhauKhac#2026');
    assert_same([422, ['xac_nhan']], [$ma, array_keys($loi ?? [])], 'Xác nhận lệch');
    [$ma, $loi] = bw_app_doi_mk('daily09', 'SaiCu#123456', 'ngan', 'khac');
    assert_same([422, ['mat_khau_cu', 'mat_khau_moi', 'xac_nhan']], [$ma, array_keys($loi ?? [])], 'Báo đủ mọi trường sai một lượt');
    assert_true(password_verify(BW_MK, (string) db_test()->query("SELECT password_hash FROM users WHERE id = $uid")->fetchColumn()), 'Sai thì không đổi gì');
}

function test_p03_t10_doi_mat_khau_khi_thue_bao_het_han_tam_khoa_chua_co(): void
{
    bw_gp_lam_sach();
    $tk = [
        'hethan10' => ['thue_bao' => ['het_han' => bw_gp_ngay(-30)]],
        'tamkhoa10' => ['thue_bao' => ['het_han' => bw_gp_ngay(30), 'tam_khoa' => 1]],
        'chuaco10' => ['thue_bao' => false],
    ];
    foreach ($tk as $ten => $them) {
        $uid = bw_app_vao($ten, $them);
        [$ma, , $r] = bw_app_doi_mk($ten, BW_MK, 'MatKhauMoi#2026');
        assert_same(200, $ma, "QĐ5: $ten phải đổi được mật khẩu, nhận: " . $r['than']);
        assert_true(password_verify('MatKhauMoi#2026', (string) db_test()->query("SELECT password_hash FROM users WHERE id = $uid")->fetchColumn()));
    }
}

function test_p03_s16_doi_mat_khau_admin_ghi_kep_bang_admins(): void
{
    bw_gp_lam_sach();
    bw_app_vao('admin16k', ['role' => 'admin']);
    db_test()->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, NOW())')->execute(['admin16k', 'cu']);
    assert_same(200, bw_app_doi_mk('admin16k', BW_MK, 'MatKhauMoi#2026')[0]);
    $hash = (string) db_test()->query("SELECT password_hash FROM admins WHERE username = 'admin16k'")->fetchColumn();
    assert_true(password_verify('MatKhauMoi#2026', $hash), 'QĐ3: bảng admins phải giữ mật khẩu mới nhất (cửa sổ rollback)');
}

/** HTML của nhap_tin.php cho tài khoản có thuê bao hết hạn ngày $hetHan (null = không có), tạm khoá $tamKhoa. */
function bw_app_trang_voi_thue_bao(string $ten, ?string $hetHan, int $tamKhoa = 0): string
{
    bw_app_vao($ten, ['thue_bao' => $hetHan === null ? false : ['het_han' => $hetHan, 'tam_khoa' => $tamKhoa]]);
    $r = bw_app_trang($ten, 'nhap_tin');
    assert_same(200, $r['ma'], "$ten: page vẫn phải mở được");
    return $r['than'];
}

function test_p03_t11_het_han_co_bang_va_khong_render_nut_ghi_phan_tich(): void
{
    bw_gp_lam_sach();
    $conHan = bw_app_trang_voi_thue_bao('conhan11', bw_gp_ngay(60));
    assert_true(str_contains($conHan, 'data-che-do="phan_tich"'), 'Đối chứng: còn hạn thì có nút phân tích');
    assert_true(!str_contains($conHan, 'data-bang-thue-bao'), 'Còn > 7 ngày: không có băng');
    $hetHan = bw_app_trang_voi_thue_bao('hethan11', bw_gp_ngay(-30));
    assert_true(str_contains($hetHan, 'data-bang-thue-bao="het_han"'), 'Phải có băng hết hạn');
    foreach (['phan_tich', 'ghi'] as $cd) {
        assert_true(!str_contains($hetHan, "data-che-do=\"$cd\""), "Hết hạn: không được render nút $cd");
    }
}

function test_p03_s16_bang_sap_het_han_an_han_tam_khoa(): void
{
    bw_gp_lam_sach();
    assert_true(str_contains(bw_app_trang_voi_thue_bao('sap16', bw_gp_ngay(5)), 'data-bang-thue-bao="sap_het_han"'), 'Còn 5 ngày → băng vàng');
    $anHan = bw_app_trang_voi_thue_bao('anhan16', bw_gp_ngay(-1));
    assert_true(str_contains($anHan, 'data-bang-thue-bao="an_han"') && str_contains($anHan, 'data-che-do="phan_tich"'), 'Ân hạn → băng đỏ, vẫn dùng được');
    foreach (['khoa16' => [bw_gp_ngay(30), 1, 'tam_khoa'], 'chuaco16' => [null, 0, 'chua_co_thue_bao']] as $ten => [$hh, $khoa, $ma]) {
        $than = bw_app_trang_voi_thue_bao($ten, $hh, $khoa);
        assert_true(str_contains($than, "data-bang-thue-bao=\"$ma\""), "$ten: băng $ma");
        assert_true(!str_contains($than, 'data-che-do='), "$ten: chỉ thấy thông báo — không render nội dung page");
    }
}
