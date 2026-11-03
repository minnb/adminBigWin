<?php
declare(strict_types=1);

// P03 S1.7 — Trang quản trị mới (admin/users.php, admin/goi_thue_bao.php) trên khung app (QĐ1), HTTP thật:
// chặn quyền (T05), CSRF (T16), tạo đại lý + mật khẩu tạm 1 lần (T13/QĐ6/M05), đăng xuất mọi nơi (T15), giấy phép (T12),
// gia hạn làm mất băng (M06), không sửa giới hạn nền tảng / không xoá gói trên UI (QĐ7).

require_once __DIR__ . '/_thue_bao.php';

const BW_TRANG_QT = ['/admin/users.php', '/admin/goi_thue_bao.php'];

/** Admin đăng nhập jar 'qt'. Trả id admin. */
function bw_qt_vao(): int
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user('quantri', ['role' => 'admin']);
    bw_http_xoa_cookie('qt');
    bw_auth_dang_nhap('qt', 'quantri');
    return $ad;
}

/** POST form trang quản trị với CSRF của phiên admin. */
function bw_qt_post(string $trang, array $truong, string $jar = 'qt'): array
{
    return bw_auth_post_form($jar, $trang, ['csrf' => bw_auth_csrf($jar)] + $truong);
}

function test_p03_t05_user_thuong_va_khach_khong_vao_duoc_quan_tri(): void
{
    bw_gp_lam_sach();
    bw_auth_tao_user('daily05');
    bw_http_xoa_cookie('t05');
    bw_auth_dang_nhap('t05', 'daily05');
    bw_http_xoa_cookie('t05k');
    foreach (BW_TRANG_QT as $trang) {
        assert_same(403, bw_http('GET', $trang, ['jar' => 't05'])['ma'], "role user GET $trang");
        assert_same(403, bw_auth_post_form('t05', $trang, ['csrf' => bw_auth_csrf('t05'), 'hanh_dong' => 'tao', 'username' => 'hack'])['ma'], "role user POST $trang");
        $r = bw_http('GET', $trang, ['jar' => 't05k']);
        assert_true($r['ma'] === 302 && str_ends_with($r['header']['location'] ?? '', 'login.php'), "Chưa đăng nhập GET $trang → login");
    }
    assert_same(0, (int) db_test()->query("SELECT COUNT(*) FROM users WHERE username = 'hack'")->fetchColumn());
}

function test_p03_s17_admin_mo_trang_quan_tri_tren_khung_app(): void
{
    bw_qt_vao();
    foreach (BW_TRANG_QT as $trang) {
        $r = bw_http('GET', $trang, ['jar' => 'qt']);
        assert_same(200, $r['ma'], $trang);
        assert_true(str_contains($r['than'], 'data-trang="quan_tri" aria-current="page"') && bw_auth_meta_csrf($r['than']) !== null, "$trang dùng khung app (QĐ1)");
    }
}

function test_p03_t16_form_quan_tri_thieu_csrf_hoac_sai_nguon_bi_403(): void
{
    bw_qt_vao();
    $csrf = bw_auth_csrf('qt');
    $truong = ['hanh_dong' => 'tao', 'username' => 'dl16', 'vai_tro' => 'user', 'goi' => 'unlimited', 'het_han' => bw_gp_ngay(30)];
    foreach (BW_TRANG_QT as $trang) {
        assert_same(403, bw_auth_post_form('qt', $trang, $truong)['ma'], "$trang thiếu CSRF");
        assert_same(403, bw_auth_post_form('qt', $trang, ['csrf' => 'sai'] + $truong)['ma'], "$trang sai CSRF");
        assert_same(403, bw_http('POST', $trang, ['jar' => 'qt', 'origin' => 'https://evil.example', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query(['csrf' => $csrf] + $truong)])['ma'], "$trang khác nguồn");
    }
    assert_same(0, (int) db_test()->query("SELECT COUNT(*) FROM users WHERE username = 'dl16'")->fetchColumn());
}

function test_p03_t13_tao_dai_ly_qua_trang_mat_khau_tam_hien_mot_lan_buoc_doi(): void
{
    bw_qt_vao();
    $r = bw_qt_post('/admin/users.php', ['hanh_dong' => 'tao', 'username' => 'dl13', 'ten_hien_thi' => 'Đại lý <b>13</b>', 'vai_tro' => 'user',
        'goi' => 'unlimited', 'het_han' => bw_gp_ngay(30)]);
    assert_same(200, $r['ma']);
    assert_true(preg_match('/data-mat-khau-tam="([A-Za-z0-9]{12})"/', $r['than'], $m) === 1, 'QĐ6: hiện mật khẩu tạm 12 ký tự');
    assert_true(!str_contains($r['than'], '<b>13</b>'), 'Tên hiển thị phải được escape');
    assert_true(!str_contains(bw_http('GET', '/admin/users.php', ['jar' => 'qt'])['than'], $m[1]), 'QĐ6: tải lại trang không còn mật khẩu tạm');
    bw_http_xoa_cookie('dl13');
    $dn = bw_auth_dang_nhap('dl13', 'dl13', $m[1]);
    assert_true(str_ends_with($dn['header']['location'] ?? '', 'doi_mat_khau.php'), 'M05: đăng nhập bằng mật khẩu tạm → buộc đổi mật khẩu');
}

function test_p03_t12_trang_bao_ro_khi_dat_gioi_han_giay_phep(): void
{
    bw_qt_vao();
    bw_auth_tao_user('dl12');
    db_test()->exec('UPDATE giay_phep_he_thong SET so_dai_ly_toi_da = 1 WHERE id = 1');
    $r = bw_qt_post('/admin/users.php', ['hanh_dong' => 'tao', 'username' => 'dl12b', 'vai_tro' => 'user', 'goi' => 'unlimited', 'het_han' => bw_gp_ngay(30)]);
    assert_true(str_contains($r['than'], 'giấy phép nền tảng (1/1'), 'Thông báo rõ giới hạn, nhận: ' . mb_substr(strip_tags($r['than']), 0, 300));
    assert_same(0, (int) db_test()->query("SELECT COUNT(*) FROM users WHERE username = 'dl12b'")->fetchColumn());
}

function test_p03_t15_dang_xuat_moi_noi_request_ke_cua_dai_ly_ve_login(): void
{
    bw_qt_vao();
    $x = bw_auth_tao_user('dl15');
    bw_http_xoa_cookie('dl15');
    bw_auth_dang_nhap('dl15', 'dl15');
    assert_same(200, bw_http('GET', '/app/nhap_tin.php', ['jar' => 'dl15'])['ma']);
    bw_qt_post('/admin/users.php', ['hanh_dong' => 'dang_xuat_moi_noi', 'id' => (string) $x]);
    $r = bw_http('GET', '/app/nhap_tin.php', ['jar' => 'dl15']);
    assert_true($r['ma'] === 302 && str_ends_with($r['header']['location'] ?? '', 'login.php'), 'Request kế của X → login');
}

function test_p03_s17_khoa_mo_khoa_doi_quyen_qua_trang(): void
{
    $ad = bw_qt_vao();
    $x = bw_auth_tao_user('dl17');
    bw_qt_post('/admin/users.php', ['hanh_dong' => 'khoa', 'id' => (string) $x]);
    assert_same('locked', db_test()->query("SELECT trang_thai FROM users WHERE id = $x")->fetchColumn());
    bw_qt_post('/admin/users.php', ['hanh_dong' => 'mo_khoa', 'id' => (string) $x]);
    assert_same('active', db_test()->query("SELECT trang_thai FROM users WHERE id = $x")->fetchColumn());
    $r = bw_qt_post('/admin/users.php', ['hanh_dong' => 'khoa', 'id' => (string) $ad]);
    assert_true(str_contains($r['than'], 'Không thể tự khoá'), 'T14 qua trang: không tự khoá');
}

function test_p03_s17_gia_han_lam_mat_bang_thue_bao_va_ghi_lich_su(): void
{
    bw_qt_vao();
    $x = bw_auth_tao_user('dl06', ['thue_bao' => ['het_han' => bw_gp_ngay(3)]]);
    bw_http_xoa_cookie('dl06');
    bw_auth_dang_nhap('dl06', 'dl06');
    assert_true(str_contains(bw_http('GET', '/app/nhap_tin.php', ['jar' => 'dl06'])['than'], 'data-bang-thue-bao="sap_het_han"'));
    bw_qt_post('/admin/goi_thue_bao.php', ['hanh_dong' => 'gia_han', 'id' => (string) $x, 'so_ngay' => '30']);
    assert_same(bw_gp_ngay(33), db_test()->query("SELECT het_han FROM thue_bao WHERE user_id = $x")->fetchColumn(), 'Gia hạn cộng từ ngày hết hạn hiện tại');
    assert_true(!str_contains(bw_http('GET', '/app/nhap_tin.php', ['jar' => 'dl06'])['than'], 'data-bang-thue-bao'), 'M06: băng biến mất ở lần tải sau');
    assert_same(['gia_han'], array_column(bw_gp_lich_su($x), 'hanh_dong'));
    $ls = bw_http('GET', "/admin/goi_thue_bao.php?lich_su=$x", ['jar' => 'qt'])['than'];
    assert_true(str_contains($ls, 'gia_han') || str_contains($ls, 'Gia hạn'), 'Trang hiển thị lịch sử thuê bao');
}

function test_p03_s17_cap_thue_bao_va_quan_ly_goi_qua_trang(): void
{
    bw_qt_vao();
    $x = bw_auth_tao_user('dl_chua', ['thue_bao' => false]);
    bw_qt_post('/admin/goi_thue_bao.php', ['hanh_dong' => 'cap_moi', 'id' => (string) $x, 'goi' => 'dung_thu', 'het_han' => bw_gp_ngay(14)]);
    assert_same('dung_thu', (new ThueBaoRepo(bw_ctx($x)))->cuaToi()['goi_ma'] ?? null, 'Cấp thuê bao cho đại lý chưa có');
    bw_qt_post('/admin/goi_thue_bao.php', ['hanh_dong' => 'them_goi', 'ma' => 'vip', 'ten' => 'Gói VIP', 'gia_thang' => '500000', 'so_ngay_an_han' => '3']);
    $id = (int) db_test()->query("SELECT id FROM goi_dich_vu WHERE ma = 'vip'")->fetchColumn();
    assert_true($id > 0, 'Thêm gói');
    bw_qt_post('/admin/goi_thue_bao.php', ['hanh_dong' => 'ngung_ban', 'id' => (string) $id]);
    assert_same(0, (int) db_test()->query("SELECT dang_ban FROM goi_dich_vu WHERE id = $id")->fetchColumn(), 'Ngừng bán');
    bw_qt_post('/admin/goi_thue_bao.php', ['hanh_dong' => 'xoa_goi', 'id' => (string) $id]);
    assert_same(1, (int) db_test()->query("SELECT COUNT(*) FROM goi_dich_vu WHERE id = $id")->fetchColumn(), 'QĐ7: không có hành động xoá gói');
}

function test_p03_s17_ui_khong_sua_gioi_han_nen_tang_va_khong_xoa_goi(): void
{
    bw_qt_vao();
    bw_auth_tao_user('dl_a');
    db_test()->exec('UPDATE giay_phep_he_thong SET so_dai_ly_toi_da = 5 WHERE id = 1');
    $than = bw_http('GET', '/admin/goi_thue_bao.php', ['jar' => 'qt'])['than'];
    assert_true(str_contains($than, 'data-giay-phep="1/5"'), 'Hiển thị "Đã dùng X/Y đại lý"');
    assert_true(!str_contains($than, 'name="so_dai_ly_toi_da"'), 'QĐ7: không có ô sửa giới hạn nền tảng');
    assert_true(preg_match('/value="xoa/', $than) === 0, 'QĐ7: không có nút xoá gói');
    foreach (bw_test_tep_php() as $duong => $tuyetDoi) {
        if (str_starts_with($duong, 'admin/') || str_starts_with($duong, 'app/')) {
            assert_true(!str_contains(file_get_contents($tuyetDoi), 'datToiDa'), "QĐ7: $duong không được sửa giới hạn nền tảng");
        }
    }
}

// ---- Bước 9 (BƯỚC CUỐI — QĐ3): admin/_auth.php cũ chuyển sang users + lib/security ----

const BW_TRANG_ADMIN_CU = ['/admin/index.php', '/admin/owners.php', '/admin/backups.php', '/admin/usage.php'];

function test_p03_t06_admin_mo_moi_trang_app_va_admin(): void
{
    bw_qt_vao();
    foreach ([...array_map(static fn (string $t): string => "/app/$t.php", ['nhap_tin', 'telegram', 'kqxs', 'khach_hang', 'lich_su', 'cu_phap']),
              ...BW_TRANG_QT, ...BW_TRANG_ADMIN_CU] as $trang) {
        $r = bw_http('GET', $trang, ['jar' => 'qt']);
        // backups.php vốn bắt buộc ?machine=<64 hex> — thiếu → 400 của chính trang (đã qua bước đăng nhập), như trước P03
        assert_same($trang === '/admin/backups.php' ? 400 : 200, $r['ma'], "Admin GET $trang — " . mb_substr(strip_tags($r['than']), 0, 200));
        assert_true(!str_contains($r['header']['set-cookie'] ?? '', 'bigwin_admin'), "$trang không còn cấp cookie bigwin_admin");
    }
}

function test_p03_t18_trang_admin_cu_chan_quyen_va_dung_phien_moi(): void
{
    bw_qt_vao();
    bw_auth_tao_user('daily18');
    bw_http_xoa_cookie('dl18');
    bw_auth_dang_nhap('dl18', 'daily18');
    bw_http_xoa_cookie('khach18');
    foreach ([...BW_TRANG_ADMIN_CU, '/admin/backup_download.php'] as $trang) {
        assert_same(403, bw_http('GET', $trang, ['jar' => 'dl18'])['ma'], "Đại lý GET $trang → 403");
        $r = bw_http('GET', $trang, ['jar' => 'khach18']);
        assert_true($r['ma'] === 302 && str_ends_with($r['header']['location'] ?? '', '../login.php'), "Chưa đăng nhập GET $trang → /login.php, nhận: " . ($r['header']['location'] ?? $r['ma']));
    }
    assert_same(404, bw_http('GET', '/admin/backup_download.php?id=999999', ['jar' => 'qt'])['ma'], 'backup_download vẫn chạy (bản không tồn tại → 404)');
}

function test_p03_t18_form_trang_admin_cu_dung_csrf_moi(): void
{
    bw_qt_vao();
    bw_test_don_bang('owners');
    assert_same(403, bw_auth_post_form('qt', '/admin/owners.php', ['ten' => 'Không token'])['ma'], 'Thiếu CSRF → 403');
    $r = bw_qt_post('/admin/owners.php', ['ten' => 'Chủ máy P03', 'ghi_chu' => '']);
    assert_true($r['ma'] === 302, 'POST hợp lệ → chuyển hướng như cũ');
    assert_same(['Chủ máy P03'], db_test()->query('SELECT ten FROM owners')->fetchAll(PDO::FETCH_COLUMN));
}

function test_p03_s17_dang_nhap_dang_xuat_admin_cu_chuyen_sang_chung(): void
{
    bw_qt_vao();
    $r = bw_http('GET', '/admin/login.php', ['jar' => 'qt']);
    assert_true($r['ma'] === 302 && str_ends_with($r['header']['location'] ?? '', '../login.php'), 'admin/login.php → /login.php');
    $r = bw_qt_post('/admin/logout.php', []);
    assert_true($r['ma'] === 302 && str_ends_with($r['header']['location'] ?? '', '../login.php'), 'admin/logout.php → đăng xuất → /login.php');
    $r = bw_http('GET', '/admin/index.php', ['jar' => 'qt']);
    assert_true($r['ma'] === 302, 'Sau đăng xuất không vào được admin cũ');
}
