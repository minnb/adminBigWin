<?php
declare(strict_types=1);

// P01 S1.3 — đăng nhập chung /login.php, phiên, đổi mật khẩu, đăng xuất — qua HTTP thật (php -S).
// Kèm xác nhận lại qua mạng các test S1.2: T09, T10, T17, T18, T19.

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/security/PhienBaoMat.php';

const BW_SAI = 'Sai tên đăng nhập hoặc mật khẩu.';

function bw_auth_user_mot(int $id): array
{
    $st = db_test()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch();
}

function test_p01_t03_trang_dang_nhap_chung_co_csrf(): void
{
    bw_http_xoa_cookie('t03');
    $r = bw_http('GET', '/login.php', ['jar' => 't03']);
    assert_same(200, $r['ma']);
    assert_true(bw_auth_meta_csrf($r['than']) !== null, 'Phải có <meta name="csrf-token">');
    assert_true(str_contains($r['than'], 'name="csrf"'), 'Form phải có trường csrf');
    assert_same('nosniff', $r['header']['x-content-type-options'] ?? null, 'Trang đăng nhập phải có tiêu đề bảo mật (T25)');
}

function test_p01_t08_dang_nhap_dung_vao_app_cookie_du_co(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    bw_http_xoa_cookie('t08');
    $r = bw_auth_dang_nhap('t08', 'daily01');
    assert_same(302, $r['ma'], 'Đăng nhập đúng phải chuyển hướng');
    assert_true(str_ends_with($r['header']['location'] ?? '', 'app/'), 'Chuyển vào app/, nhận: ' . ($r['header']['location'] ?? ''));
    $c = $r['header']['set-cookie'] ?? '';
    foreach (['HttpOnly', 'SameSite=Lax'] as $co) {
        assert_true(stripos($c, $co) !== false, "T09 qua đăng nhập thật: Set-Cookie thiếu $co ($c)");
    }
    $app = bw_http('GET', '/app/', ['jar' => 't08']);
    assert_same(200, $app['ma']);
    assert_true(str_contains($app['than'], 'daily01'), 'Trang app phải nhận ra người đăng nhập');
}

function test_p01_t10_http_dang_nhap_doi_session_id(): void
{
    bw_auth_lam_sach();
    $uid = bw_auth_tao_user('daily01');
    bw_http_xoa_cookie('t10h');
    $truoc = bw_http('GET', '/login.php', ['jar' => 't10h'])['cookie'][PhienBaoMat::TEN] ?? null;
    assert_true($truoc !== null, 'Mở trang đăng nhập phải cấp phiên (để giữ CSRF)');
    $sau = bw_auth_dang_nhap('t10h', 'daily01')['cookie'][PhienBaoMat::TEN] ?? null;
    assert_true($sau !== null && $sau !== $truoc, 'Đăng nhập thật phải đổi session ID');
    $st = db_test()->prepare('SELECT user_id FROM phien_dang_nhap WHERE id_hash = ?');
    $st->execute([PhienBaoMat::bamId($sau)]);
    assert_same($uid, (int) $st->fetchColumn());
}

function test_p01_t11_thong_bao_loi_chung_khong_lo_tai_khoan(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    $saiMk = bw_auth_dang_nhap('t11a', 'daily01', 'SaiMatKhau#1');
    $khongCo = bw_auth_dang_nhap('t11b', 'khong_ton_tai', 'SaiMatKhau#1');
    foreach ([$saiMk, $khongCo] as $r) {
        assert_same(200, $r['ma'], 'Sai thì ở lại trang đăng nhập');
        assert_true(str_contains($r['than'], BW_SAI), 'Phải đúng câu lỗi chung');
    }
    $loi = static fn (string $html): string => preg_replace('/<meta name="csrf-token"[^>]*>|name="(?:csrf|username)" value="[^"]*"/', '', $html);
    assert_same($loi($saiMk['than']), $loi($khongCo['than']), 'Sai mật khẩu và sai username phải trả cùng một trang (trừ token và ô username điền sẵn)');
}

function test_p01_t12_khoa_15_phut_sau_5_lan_sai(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    for ($i = 0; $i < 5; $i++) {
        bw_auth_dang_nhap('t12', 'daily01', 'SaiMatKhau#' . $i);
    }
    $r = bw_auth_dang_nhap('t12', 'daily01');
    assert_same(200, $r['ma'], 'Lần 6 với mật khẩu ĐÚNG vẫn bị chặn');
    assert_true(str_contains($r['than'], '15 phút'), 'Phải báo khoá 15 phút');
    db_test()->exec('UPDATE user_login_fails SET created_at = NOW() - INTERVAL 16 MINUTE');
    assert_same(302, bw_auth_dang_nhap('t12', 'daily01')['ma'], 'Hết 15 phút → đăng nhập được');
    assert_same(0, bw_test_dem('user_login_fails'), 'Đăng nhập đúng → xoá đếm sai');
}

function test_p01_t13_tai_khoan_bi_khoa_khong_dang_nhap_duoc(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01', ['trang_thai' => 'locked']);
    $r = bw_auth_dang_nhap('t13', 'daily01');
    assert_same(200, $r['ma']);
    assert_true(str_contains($r['than'], 'đã bị khoá'), 'Mật khẩu đúng nhưng tài khoản khoá → báo khoá');
    assert_same(0, bw_test_dem('phien_dang_nhap'), 'Không được tạo phiên');
}

function test_p01_t14_khoa_tai_khoan_giua_phien_bi_day_ra_ngay(): void
{
    bw_auth_lam_sach();
    $uid = bw_auth_tao_user('daily01');
    bw_auth_dang_nhap('t14', 'daily01');
    $csrf = bw_auth_csrf('t14');
    assert_same(200, bw_ping('t14', ['csrf' => $csrf])['ma']);
    db_test()->exec("UPDATE users SET trang_thai = 'locked' WHERE id = $uid");
    assert_same(401, bw_ping('t14', ['csrf' => $csrf])['ma'], 'Request kế sau khi khoá → 401');
}

function test_p01_t15_buoc_doi_mat_khau_lan_dau(): void
{
    bw_auth_lam_sach();
    $uid = bw_auth_tao_user('daily01', ['phai_doi_mat_khau' => 1]);
    $r = bw_auth_dang_nhap('t15', 'daily01');
    assert_true(str_ends_with($r['header']['location'] ?? '', 'doi_mat_khau.php'), 'Đăng nhập lần đầu → trang đổi mật khẩu');
    assert_true(str_ends_with(bw_http('GET', '/app/', ['jar' => 't15'])['header']['location'] ?? '', 'doi_mat_khau.php'), 'Mọi trang app → đổi mật khẩu');
    $p = bw_ping('t15');
    assert_same([403, 'phai_doi_mat_khau'], [$p['ma'], $p['json']['loi'] ?? null]);
    $moi = 'MatKhauMoi#2026';
    $r = bw_auth_post_form('t15', '/doi_mat_khau.php', ['csrf' => bw_auth_csrf('t15'), 'mat_khau_cu' => BW_MK, 'mat_khau_moi' => $moi, 'xac_nhan' => $moi]);
    assert_same(302, $r['ma'], 'Đổi đúng → chuyển vào app');
    $u = bw_auth_user_mot($uid);
    assert_same(0, (int) $u['phai_doi_mat_khau']);
    assert_true(password_verify($moi, $u['password_hash']));
    assert_same(200, bw_ping('t15')['ma'], 'Đổi xong dùng được hệ thống');
}

function test_p01_t15_doi_mat_khau_tu_choi_dau_vao_sai(): void
{
    bw_auth_lam_sach();
    $uid = bw_auth_tao_user('daily01', ['phai_doi_mat_khau' => 1]);
    bw_auth_dang_nhap('t15b', 'daily01');
    foreach ([
        ['SaiCu#123456', 'MatKhauMoi#2026', 'MatKhauMoi#2026', 'Mật khẩu hiện tại không đúng'],
        [BW_MK, 'ngan123', 'ngan123', 'ít nhất 10 ký tự'],
        [BW_MK, 'MatKhauMoi#2026', 'MatKhauKhac#2026', 'không khớp'],
        [BW_MK, BW_MK, BW_MK, 'khác mật khẩu hiện tại'],
    ] as [$cu, $moi, $xn, $canBao]) {
        $r = bw_auth_post_form('t15b', '/doi_mat_khau.php', ['csrf' => bw_auth_csrf('t15b'), 'mat_khau_cu' => $cu, 'mat_khau_moi' => $moi, 'xac_nhan' => $xn]);
        assert_same(200, $r['ma']);
        assert_true(str_contains($r['than'], $canBao), "Phải báo: $canBao");
    }
    assert_same(1, (int) bw_auth_user_mot($uid)['phai_doi_mat_khau'], 'Đầu vào sai không được đổi gì');
}

function test_p01_t16_doi_mat_khau_thu_hoi_phien_khac(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    bw_auth_dang_nhap('t16a', 'daily01');
    bw_auth_dang_nhap('t16b', 'daily01');
    $csrfB = bw_auth_csrf('t16b');
    $moi = 'MatKhauMoi#2026';
    bw_auth_post_form('t16a', '/doi_mat_khau.php', ['csrf' => bw_auth_csrf('t16a'), 'mat_khau_cu' => BW_MK, 'mat_khau_moi' => $moi, 'xac_nhan' => $moi]);
    assert_same(401, bw_ping('t16b', ['csrf' => $csrfB])['ma'], 'Trình duyệt khác bị đăng xuất');
    assert_same(200, bw_ping('t16a')['ma'], 'Phiên vừa đổi mật khẩu vẫn dùng tiếp');
}

function test_p01_t17_http_nhan_roi_qua_30_phut(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    bw_auth_dang_nhap('t17', 'daily01');
    $csrf = bw_auth_csrf('t17');
    db_test()->exec('UPDATE phien_dang_nhap SET hoat_dong_luc = NOW() - INTERVAL 31 MINUTE');
    assert_same(401, bw_ping('t17', ['csrf' => $csrf])['ma']);
}

function test_p01_t18_http_tuyet_doi_qua_12_gio(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    bw_auth_dang_nhap('t18', 'daily01');
    $csrf = bw_auth_csrf('t18');
    db_test()->exec('UPDATE phien_dang_nhap SET tao_luc = NOW() - INTERVAL 13 HOUR');
    assert_same(401, bw_ping('t18', ['csrf' => $csrf])['ma']);
}

function test_p01_t19_http_doi_user_agent(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    bw_auth_dang_nhap('t19', 'daily01');
    $csrf = bw_auth_csrf('t19');
    assert_same(401, bw_ping('t19', ['csrf' => $csrf, 'headers' => ['User-Agent' => 'TrinhDuyetKhac/1.0']])['ma']);
}

function test_p01_s13_dang_xuat_thu_hoi_phien(): void
{
    bw_auth_lam_sach();
    bw_auth_tao_user('daily01');
    bw_auth_dang_nhap('dx', 'daily01');
    $csrf = bw_auth_csrf('dx');
    $r = bw_auth_post_form('dx', '/logout.php', ['csrf' => $csrf]);
    assert_same(302, $r['ma']);
    assert_true(str_ends_with($r['header']['location'] ?? '', 'login.php'));
    assert_same(1, (int) db_test()->query('SELECT COUNT(*) FROM phien_dang_nhap WHERE thu_hoi_luc IS NOT NULL')->fetchColumn(), 'Sổ phiên ghi thu hồi');
    assert_same(401, bw_ping('dx', ['csrf' => $csrf])['ma']);
}
