<?php
declare(strict_types=1);

// P03 S1.7 — UserRepo (QĐ4: lớp quản trị độc lập), GoiDichVuRepo (QĐ7: không xoá gói), CLI giấy phép nền tảng (QĐ7),
// mật khẩu tạm (QĐ6), ghi kép bảng admins (QĐ3).

require_once __DIR__ . '/_thue_bao.php';
require_once __DIR__ . '/../lib/bigwin/repo/UserRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/GoiDichVuRepo.php';

/** Admin + repo quản trị trên DB sạch. Trả [id admin, UserRepo]. */
function bw_ur_admin(string $ten = 'quantri'): array
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user($ten, ['role' => 'admin']);
    return [$ad, new UserRepo(bw_ctx($ad, 'admin'))];
}

function bw_ur_dong(int $id): array
{
    $st = db_test()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch();
}

function bw_ur_dat_gioi_han(?int $toiDa): void
{
    db_test()->prepare('UPDATE giay_phep_he_thong SET so_dai_ly_toi_da = ? WHERE id = 1')->execute([$toiDa]);
}

/** Tạo phiên đăng nhập (sổ phiên) cho $uid; trả số phiên còn hiệu lực. */
function bw_ur_phien_con(int $uid, int $themPhien = 0): int
{
    for ($i = 0; $i < $themPhien; $i++) {
        db_test()->prepare("INSERT INTO phien_dang_nhap (id_hash, user_id, ip, ua_hash, tao_luc, hoat_dong_luc) VALUES (?, ?, '127.0.0.1', ?, NOW(), NOW())")
            ->execute([hash('sha256', random_bytes(8)), $uid, str_repeat('a', 64)]);
    }
    return (int) db_test()->query("SELECT COUNT(*) FROM phien_dang_nhap WHERE user_id = $uid AND thu_hoi_luc IS NULL")->fetchColumn();
}

function test_p03_t13_admin_tao_dai_ly_buoc_doi_mat_khau_co_thue_bao_va_lich_su(): void
{
    [$ad, $repo] = bw_ur_admin();
    $kq = $repo->tao('daily13', 'user', 'Đại lý 13', 'dung_thu', today_vn()->modify('+14 day'));
    $u = bw_ur_dong($kq['id']);
    assert_same(['daily13', 'user', 'active', 1, 'Đại lý 13', $ad], [$u['username'], $u['role'], $u['trang_thai'], (int) $u['phai_doi_mat_khau'], $u['ten_hien_thi'], (int) $u['created_by']]);
    assert_true(password_verify($kq['mat_khau_tam'], $u['password_hash']), 'Mật khẩu tạm phải đăng nhập được');
    $tb = (new ThueBaoRepo(bw_ctx($kq['id'])))->cuaToi();
    assert_same(['dung_thu', bw_gp_ngay(14)], [$tb['goi_ma'], $tb['het_han']]);
    assert_same([['tao', $ad]], array_map(static fn (array $d): array => [$d['hanh_dong'], $d['boi']], bw_gp_lich_su($kq['id'])));
    assert_same(0, bw_test_dem('admins'), 'Đại lý không ghi vào bảng admins');
}

function test_p03_s17_mat_khau_tam_ngau_nhien_12_ky_tu_khong_ky_tu_de_nham(): void
{
    [, $repo] = bw_ur_admin();
    $mk = [];
    for ($i = 0; $i < 5; $i++) {
        $mk[] = $repo->tao("dl_mk$i", 'user', '', 'unlimited', today_vn()->modify('+1 day'))['mat_khau_tam'];
    }
    assert_same(5, count(array_unique($mk)), 'Mỗi lần sinh một mật khẩu khác');
    foreach ($mk as $m) {
        assert_true(strlen($m) === UserRepo::DO_DAI_MAT_KHAU_TAM && strlen($m) >= MAT_KHAU_TOI_THIEU && preg_match('/^[A-HJ-NP-Za-km-z2-9]+$/', $m) === 1,
            "QĐ6: 12 ký tự chữ-số, không có 0 O 1 l I — nhận: $m");
    }
}

function test_p03_s17_tao_admin_ghi_kep_bang_admins_khong_can_thue_bao(): void
{
    [, $repo] = bw_ur_admin();
    $kq = $repo->tao('quantri2', 'admin');
    $hash = (string) db_test()->query("SELECT password_hash FROM admins WHERE username = 'quantri2'")->fetchColumn();
    assert_true(password_verify($kq['mat_khau_tam'], $hash), 'QĐ3: admin mới có trong bảng admins cùng mật khẩu');
    assert_same(null, (new ThueBaoRepo(bw_ctx($kq['id'], 'admin')))->cuaToi());
}

function test_p03_s17_du_lieu_tao_sai_bi_tu_choi(): void
{
    [, $repo] = bw_ur_admin();
    $repo->tao('trung_ten', 'user', '', 'unlimited', today_vn()->modify('+1 day'));
    $hh = today_vn()->modify('+1 day');
    foreach ([
        'tên quá ngắn' => static fn () => $repo->tao('ab', 'user', '', 'unlimited', $hh),
        'tên có dấu cách' => static fn () => $repo->tao('dai ly', 'user', '', 'unlimited', $hh),
        'vai trò lạ' => static fn () => $repo->tao('daily_x', 'root'),
        'đại lý thiếu gói' => static fn () => $repo->tao('daily_y', 'user'),
        'gói không tồn tại' => static fn () => $repo->tao('daily_z', 'user', '', 'khong_co', $hh),
        'trùng tên (không phân biệt hoa thường)' => static fn () => $repo->tao('TRUNG_TEN', 'user', '', 'unlimited', $hh),
    ] as $vi => $fn) {
        assert_true(bw_test_loi_lop($fn) !== null, "Phải từ chối: $vi");
    }
    assert_same(['quantri', 'trung_ten'], db_test()->query('SELECT username FROM users ORDER BY username')->fetchAll(PDO::FETCH_COLUMN),
        'Tạo lỗi không để lại tài khoản / thuê bao dở dang');
    assert_same(1, bw_test_dem('thue_bao'));
}

function test_p03_t12_tao_dai_ly_khi_dat_gioi_han_giay_phep_bi_chan(): void
{
    [, $repo] = bw_ur_admin();
    $hh = today_vn()->modify('+1 day');
    $repo->tao('dl12a', 'user', '', 'unlimited', $hh);
    $repo->tao('dl12b', 'user', '', 'unlimited', $hh);
    bw_ur_dat_gioi_han(2);
    $loi = bw_test_bat_loi(static fn () => $repo->tao('dl12c', 'user', '', 'unlimited', $hh));
    assert_true($loi !== null && str_contains($loi, 'giấy phép') && str_contains($loi, '2/2'), 'Thông báo rõ giới hạn X/Y, nhận: ' . var_export($loi, true));
    assert_same(LoiQuanTri::class, bw_test_loi_lop(static fn () => $repo->tao('dl12c', 'user', '', 'unlimited', $hh)));
    assert_same(0, (int) db_test()->query("SELECT COUNT(*) FROM users WHERE username = 'dl12c'")->fetchColumn());
    assert_true($repo->tao('quantri12', 'admin')['id'] > 0, 'Admin không tính vào giấy phép nền tảng (O8)');
}

function test_p03_t14_khong_tu_khoa_khong_ha_admin_cuoi(): void
{
    [$ad, $repo] = bw_ur_admin();
    foreach (['tự khoá' => static fn () => $repo->khoa($ad), 'tự hạ quyền' => static fn () => $repo->doiVaiTro($ad, 'user'),
              'tự đặt lại mật khẩu' => static fn () => $repo->datLaiMatKhau($ad)] as $vi => $fn) {
        assert_same(LoiQuanTri::class, bw_test_loi_lop($fn), "Chặn: $vi");
    }
    $repoHeThong = new UserRepo(bw_voi_nhat_ky(static fn () => TenantContext::heThong('test T14'))[0]);
    foreach (['khoá admin cuối' => static fn () => $repoHeThong->khoa($ad), 'hạ admin cuối' => static fn () => $repoHeThong->doiVaiTro($ad, 'user')] as $vi => $fn) {
        $loi = bw_test_bat_loi($fn);
        assert_true($loi !== null && str_contains($loi, 'ít nhất 1 quản trị'), "Chặn: $vi — nhận: " . var_export($loi, true));
    }
    assert_same(['admin', 'active'], [bw_ur_dong($ad)['role'], bw_ur_dong($ad)['trang_thai']]);
    $ad2 = $repo->tao('quantri2', 'admin')['id'];
    $repo->doiVaiTro($ad2, 'user');
    assert_same('user', bw_ur_dong($ad2)['role'], 'Còn admin khác thì hạ quyền được');
}

function test_p03_t15_dang_xuat_moi_noi_thu_hoi_moi_phien(): void
{
    [, $repo] = bw_ur_admin();
    $x = bw_auth_tao_user('dl15');
    assert_same(3, bw_ur_phien_con($x, 3));
    $repo->dangXuatMoiNoi($x);
    assert_same(0, bw_ur_phien_con($x));
}

function test_p03_s17_khoa_va_dat_lai_mat_khau_thu_hoi_phien(): void
{
    [, $repo] = bw_ur_admin();
    $x = bw_auth_tao_user('dl17');
    bw_ur_phien_con($x, 2);
    $mk = $repo->datLaiMatKhau($x);
    $u = bw_ur_dong($x);
    assert_true(password_verify($mk, $u['password_hash']) && (int) $u['phai_doi_mat_khau'] === 1, 'Đặt lại → mật khẩu tạm + buộc đổi');
    assert_same(0, bw_ur_phien_con($x), 'Đặt lại mật khẩu thu hồi mọi phiên');
    bw_ur_phien_con($x, 1);
    $repo->khoa($x);
    assert_same(['locked', 0], [bw_ur_dong($x)['trang_thai'], bw_ur_phien_con($x)]);
}

function test_p03_s17_dat_lai_mat_khau_admin_ghi_kep(): void
{
    [, $repo] = bw_ur_admin();
    $ad2 = $repo->tao('quantri2', 'admin')['id'];
    $mk = $repo->datLaiMatKhau($ad2);
    assert_true(password_verify($mk, (string) db_test()->query("SELECT password_hash FROM admins WHERE username = 'quantri2'")->fetchColumn()), 'QĐ3');
}

function test_p03_t17_mo_khoa_dai_ly_khi_vuot_giay_phep_bi_chan(): void
{
    [, $repo] = bw_ur_admin();
    $a = bw_auth_tao_user('dl17a');
    bw_auth_tao_user('dl17b');
    $repo->khoa($a);
    bw_ur_dat_gioi_han(1);
    assert_same(LoiQuanTri::class, bw_test_loi_lop(static fn () => $repo->moKhoa($a)));
    assert_same('locked', bw_ur_dong($a)['trang_thai']);
    bw_ur_dat_gioi_han(2);
    $repo->moKhoa($a);
    assert_same('active', bw_ur_dong($a)['trang_thai']);
    $ad2 = $repo->tao('quantri2', 'admin')['id'];
    assert_same(LoiQuanTri::class, bw_test_loi_lop(static fn () => $repo->doiVaiTro($ad2, 'user')), 'Hạ admin thành đại lý cũng tính giấy phép');
}

function test_p03_s17_user_repo_chi_cho_quan_tri(): void
{
    bw_gp_lam_sach();
    $u = bw_auth_tao_user('dl_thuong');
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => new UserRepo(bw_ctx($u))), 'QĐ4: ngữ cảnh đại lý không dùng được UserRepo');
}

// ---- Gói dịch vụ (QĐ7) ----

function test_p03_s17_goi_them_sua_ngung_ban_khong_xoa(): void
{
    [$ad] = bw_ur_admin();
    $repo = new GoiDichVuRepo(bw_ctx($ad, 'admin'));
    $id = $repo->themGoi(['ma' => 'vip', 'ten' => 'Gói VIP', 'gia_thang' => '500000', 'so_tin_ngay_toi_da' => '200', 'so_khach_toi_da' => '', 'so_ngay_an_han' => '5']);
    $goi = array_column($repo->danhSach(), null, 'ma')['vip'];
    assert_same(['Gói VIP', 500000, 200, null, 5, 1], [$goi['ten'], (int) $goi['gia_thang'], $goi['so_tin_ngay_toi_da'], $goi['so_khach_toi_da'], (int) $goi['so_ngay_an_han'], (int) $goi['dang_ban']]);
    $repo->suaGoi($id, ['ten' => 'VIP 2', 'gia_thang' => '600000', 'so_tin_ngay_toi_da' => '', 'so_khach_toi_da' => '50', 'so_ngay_an_han' => '3']);
    $repo->datDangBan($id, false);
    $goi = array_column($repo->danhSach(), null, 'ma')['vip'];
    assert_same(['VIP 2', null, 50, 0], [$goi['ten'], $goi['so_tin_ngay_toi_da'], (int) $goi['so_khach_toi_da'], (int) $goi['dang_ban']]);
    foreach ([['ma' => 'vip', 'ten' => 'Trùng'], ['ma' => 'Có Dấu', 'ten' => 'x'], ['ma' => 'ok', 'ten' => ''], ['ma' => 'ok2', 'ten' => 'x', 'gia_thang' => '-1']] as $sai) {
        assert_true(bw_test_loi_lop(static fn () => $repo->themGoi($sai)) !== null, 'Gói sai phải bị từ chối: ' . json_encode($sai, JSON_UNESCAPED_UNICODE));
    }
    $congKhai = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass(GoiDichVuRepo::class))->getMethods(ReflectionMethod::IS_PUBLIC));
    assert_same([], array_values(array_filter($congKhai, static fn (string $m): bool => preg_match('/xoa|delete|remove/i', $m) === 1)), 'QĐ7: không có method xoá gói');
    $repoDaiLy = new GoiDichVuRepo(bw_ctx(bw_auth_tao_user('dl_goi')));
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repoDaiLy->themGoi(['ma' => 'x1', 'ten' => 'x'])), 'Đại lý không ghi được gói');
}

function test_p03_s17_cli_giay_phep_nen_tang(): void
{
    bw_gp_lam_sach();
    $chay = static fn (string ...$ts): array => bw_test_chay([PHP_BINARY, BW_WEBAPP_DIR . '/tools/giay_phep_nen_tang.php', ...$ts]);
    [$ma, $ra] = $chay('5', 'Gói 5 đại lý');
    assert_same(0, $ma, $ra);
    assert_same([5, 'Gói 5 đại lý'], array_values(db_test()->query('SELECT so_dai_ly_toi_da, ghi_chu FROM giay_phep_he_thong WHERE id = 1')->fetch()));
    [$ma, $ra] = $chay();
    assert_true($ma === 0 && str_contains($ra, '0/5'), "Không tham số → in hiện trạng X/Y: $ra");
    assert_same(0, $chay('khong-gioi-han')[0]);
    assert_same(null, db_test()->query('SELECT so_dai_ly_toi_da FROM giay_phep_he_thong WHERE id = 1')->fetchColumn());
    assert_same(1, $chay('-3')[0], 'Giá trị sai → thoát 1');
}
