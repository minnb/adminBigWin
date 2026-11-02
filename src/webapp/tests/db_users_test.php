<?php
declare(strict_types=1);

// P01 S1.1 — bảng users (RBAC) + chuyển admin cũ sang + tools/create_admin.php (--role, --phai-doi-mat-khau).

require_once __DIR__ . '/_db.php';

const BW_MIGRATION_USERS = BW_WEBAPP_DIR . '/migrations/2026-11-02_users_rbac_bao_mat.sql';

/** DB test sạch cho nhóm bảng danh tính. */
function bw_users_lam_sach(): void
{
    bw_test_don_bang('phien_dang_nhap', 'user_login_fails', 'gioi_han_tan_suat', 'users', 'admins');
}

/** Chạy migration S1.1 trên DB test (như nâng cấp một DB đã có). */
function bw_users_chay_migration(): void
{
    bw_test_chay_sql(db_test(), file_get_contents(BW_MIGRATION_USERS));
}

function bw_users_them_admin_cu(string $ten, string $matKhau): string
{
    $hash = password_hash($matKhau, PASSWORD_BCRYPT, ['cost' => 4]);
    db_test()->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, NOW())')->execute([$ten, $hash]);
    return $hash;
}

/** Chạy tools/create_admin.php trong tiến trình con (cùng cấu hình test). Trả [mã thoát, output]. */
function bw_users_chay_tool(string ...$thamSo): array
{
    [$ma, $ra, $loi] = bw_test_chay([PHP_BINARY, BW_WEBAPP_DIR . '/tools/create_admin.php', ...$thamSo]);
    return [$ma, $ra . $loi];
}

function bw_users_mot(string $ten): ?array
{
    $st = db_test()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$ten]);
    return $st->fetch() ?: null;
}

function test_p01_t04_migration_copy_du_admin_cu_sang_users(): void
{
    bw_users_lam_sach();
    bw_users_them_admin_cu('quantri', 'MatKhauCu#2026');
    bw_users_them_admin_cu('quantri2', 'MatKhauKhac#2026');
    bw_users_chay_migration();
    bw_users_chay_migration(); // chạy lại phải an toàn: không nhân đôi admin
    $users = db_test()->query("SELECT username, role, trang_thai, phai_doi_mat_khau FROM users ORDER BY username")->fetchAll();
    assert_same([
        ['username' => 'quantri', 'role' => 'admin', 'trang_thai' => 'active', 'phai_doi_mat_khau' => 0],
        ['username' => 'quantri2', 'role' => 'admin', 'trang_thai' => 'active', 'phai_doi_mat_khau' => 0],
    ], $users);
    assert_same(2, bw_test_dem('admins'), 'Bảng admins cũ giữ nguyên (admin/_auth.php còn dùng tới P03)');
}

function test_p01_t05_mat_khau_cu_van_dung_sau_khi_chuyen(): void
{
    bw_users_lam_sach();
    $hash = bw_users_them_admin_cu('quantri', 'MatKhauCu#2026');
    bw_users_chay_migration();
    $u = bw_users_mot('quantri');
    assert_same($hash, $u['password_hash'], 'password_hash phải giữ nguyên văn');
    assert_true(password_verify('MatKhauCu#2026', $u['password_hash']), 'Mật khẩu cũ phải đăng nhập được');
    assert_true(!password_verify('sai-mat-khau', $u['password_hash']));
}

function test_p01_t06_username_unique_khong_phan_biet_hoa_thuong(): void
{
    bw_users_lam_sach();
    $them = db_test()->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (?, 'x', NOW())");
    $them->execute(['daily01']);
    foreach (['daily01', 'DaiLy01'] as $trung) {
        $loi = bw_test_bat_loi(static fn () => $them->execute([$trung]));
        assert_true($loi !== null && str_contains($loi, '1062'), "Chèn trùng username '$trung' phải bị UNIQUE chặn, nhận: " . var_export($loi, true));
    }
}

function test_p01_t07_create_admin_tao_dai_ly_buoc_doi_mat_khau(): void
{
    bw_users_lam_sach();
    [$ma, $ra] = bw_users_chay_tool('daily01', 'MatKhau@12345', '--role=user', '--phai-doi-mat-khau');
    assert_same(0, $ma, "Tool phải thành công, output: $ra");
    $u = bw_users_mot('daily01');
    assert_same(['user', 'active', 1], [$u['role'], $u['trang_thai'], (int) $u['phai_doi_mat_khau']]);
    assert_true(password_verify('MatKhau@12345', $u['password_hash']));
    assert_same(0, bw_test_dem('admins'), 'Đại lý (role user) không được ghi vào bảng admins cũ');
}

function test_p01_t07_create_admin_mac_dinh_la_admin_va_dong_bo_bang_cu(): void
{
    bw_users_lam_sach();
    [$ma, $ra] = bw_users_chay_tool('quantri', 'MatKhau@12345');
    assert_same(0, $ma, "Tool phải thành công, output: $ra");
    $u = bw_users_mot('quantri');
    assert_same(['admin', 0], [$u['role'], (int) $u['phai_doi_mat_khau']]);
    $cu = db_test()->query("SELECT password_hash FROM admins WHERE username = 'quantri'")->fetchColumn();
    assert_same($u['password_hash'], $cu, 'Admin phải đăng nhập được /admin cũ (bảng admins, cùng hash) tới khi P03 chuyển _auth.php');
}

function test_p01_t07_create_admin_doi_mat_khau_va_quyen_tai_khoan_da_co(): void
{
    bw_users_lam_sach();
    bw_users_chay_tool('daily01', 'MatKhau@12345', '--role=user');
    [$ma] = bw_users_chay_tool('daily01', 'MatKhauMoi@6789', '--role=user', '--phai-doi-mat-khau');
    assert_same(0, $ma);
    $u = bw_users_mot('daily01');
    assert_true(password_verify('MatKhauMoi@6789', $u['password_hash']), 'Chạy lại = đặt lại mật khẩu');
    assert_same(1, (int) $u['phai_doi_mat_khau']);
    assert_same(1, bw_test_dem('users'), 'Không tạo tài khoản trùng');
}

function test_p01_t07_create_admin_tu_choi_tham_so_sai(): void
{
    bw_users_lam_sach();
    foreach ([
        ['daily01', 'MatKhau@12345', '--role=sieuquantri'], // role lạ
        ['daily01', 'ngan123'],                              // mật khẩu < 10 ký tự
        ['', 'MatKhau@12345'],                               // thiếu username
        ['daily01', 'MatKhau@12345', '--co-la'],             // cờ không hỗ trợ
    ] as $thamSo) {
        [$ma] = bw_users_chay_tool(...$thamSo);
        assert_same(1, $ma, 'Phải từ chối: ' . implode(' ', $thamSo));
    }
    assert_same(0, bw_test_dem('users'), 'Tham số sai không được ghi gì');
}
