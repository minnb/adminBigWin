<?php
declare(strict_types=1);

// P01 S1.3 — AJAX gateway ajax_khoi_dong() qua endpoint thử app/ajax/_ping.php (HTTP thật).
// Kèm xác nhận lại qua mạng các test S1.2: T25 (tiêu đề), T26 (rate limit).

require_once __DIR__ . '/_thue_bao.php';

/** Đăng nhập jar mới cho tài khoản $ten (đã tạo sẵn). */
function bw_gw_vao(string $jar, string $ten): void
{
    bw_http_xoa_cookie($jar);
    bw_auth_dang_nhap($jar, $ten);
}

/** [mã HTTP, mã lỗi JSON `loi`] của một phản hồi gateway. */
function bw_gw_ma_loi(array $r): array
{
    return [$r['ma'], $r['json']['loi'] ?? null];
}

/** Đăng nhập jar mới, trả [uid, csrf]. */
function bw_gw_san_sang(string $jar): array
{
    bw_auth_lam_sach();
    $uid = bw_auth_tao_user('daily01');
    bw_gw_vao($jar, 'daily01');
    return [$uid, bw_auth_csrf($jar)];
}

function test_p01_s13_gateway_tra_ok_khi_du_dieu_kien(): void
{
    [$uid, $csrf] = bw_gw_san_sang('gw');
    $r = bw_ping('gw', ['csrf' => $csrf]);
    assert_same(200, $r['ma']);
    assert_same(['ok' => true, 'uid' => $uid], $r['json']);
}

function test_p01_t20_thieu_csrf_tra_403(): void
{
    [, $csrf] = bw_gw_san_sang('t20');
    foreach ([null, 'sai-token', strrev($csrf)] as $tok) {
        $r = bw_ping('t20', ['csrf' => $tok]);
        assert_same([403, false, 403, 'csrf'], [$r['ma'], $r['json']['ok'] ?? null, $r['json']['ma'] ?? null, $r['json']['loi'] ?? null],
            'Token: ' . var_export($tok, true));
    }
}

function test_p01_t21_csrf_dung_nhung_khac_origin_tra_403(): void
{
    [, $csrf] = bw_gw_san_sang('t21');
    assert_same(403, bw_ping('t21', ['csrf' => $csrf, 'origin' => 'https://evil.example'])['ma']);
    assert_same(403, bw_ping('t21', ['csrf' => $csrf, 'origin' => false])['ma'], 'Thiếu cả Origin lẫn Referer → 403');
    assert_same(403, bw_ping('t21', ['csrf' => $csrf, 'headers' => ['Sec-Fetch-Site' => 'cross-site']])['ma']);
}

function test_p01_t22_get_vao_endpoint_chi_post_tra_405(): void
{
    bw_gw_san_sang('t22');
    $r = bw_http('GET', '/app/ajax/_ping.php', ['jar' => 't22']);
    assert_same([405, 'sai_phuong_thuc'], [$r['ma'], $r['json']['loi'] ?? null]);
    assert_same('POST', $r['header']['allow'] ?? null);
}

function test_p01_t23_body_hoac_content_type_sai_tra_422(): void
{
    [, $csrf] = bw_gw_san_sang('t23');
    $goi = static fn (array $tuyChon): array => bw_http('POST', '/app/ajax/_ping.php', ['jar' => 't23', 'csrf' => $csrf] + $tuyChon);
    $r = $goi(['headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'body' => 'a=1']);
    assert_same([422, 'sai_dinh_dang'], [$r['ma'], $r['json']['loi'] ?? null], 'Sai Content-Type');
    $r = $goi(['json' => ['x' => str_repeat('a', 70_000)]]);
    assert_same([422, 'qua_lon'], [$r['ma'], $r['json']['loi'] ?? null], 'Body > 64 KB');
    $r = $goi(['headers' => ['Content-Type' => 'application/json'], 'body' => '{khong phai json']);
    assert_same([422, 'json_sai'], [$r['ma'], $r['json']['loi'] ?? null], 'JSON hỏng');
}

function test_p01_t24_exception_tra_500_khong_lo_chi_tiet_co_ma_tham_chieu(): void
{
    [, $csrf] = bw_gw_san_sang('t24');
    $r = bw_ping('t24', ['csrf' => $csrf, 'json' => ['nem_loi' => true]]);
    assert_same(500, $r['ma']);
    $ref = $r['json']['ma_tham_chieu'] ?? '';
    assert_true(preg_match('/^[0-9a-f]{8,}$/', $ref) === 1, 'Phải có mã tham chiếu, nhận: ' . $r['than']);
    foreach (['RuntimeException', 'bi_mat', '.php', 'Stack', '#0'] as $cam) {
        assert_true(!str_contains($r['than'], $cam), "Phản hồi lộ chi tiết lỗi: $cam");
    }
    assert_true(str_contains((string) file_get_contents(bw_http_nhat_ky()), "BigWIN [$ref]"), 'Lỗi đầy đủ phải được ghi_loi() kèm mã tham chiếu');
}

function test_p01_t25_http_tieu_de_bao_mat_tren_json(): void
{
    [, $csrf] = bw_gw_san_sang('t25');
    $h = bw_ping('t25', ['csrf' => $csrf])['header'];
    assert_same('nosniff', $h['x-content-type-options'] ?? null);
    assert_true(str_contains($h['cache-control'] ?? '', 'no-store'), 'JSON phải có no-store, nhận: ' . ($h['cache-control'] ?? ''));
    assert_true(str_starts_with($h['content-type'] ?? '', 'application/json'));
    // Phiên PHP (cache_limiter nocache) gửi lại Cache-Control sau gateway: "no-store, no-cache, must-revalidate" — vẫn cấm lưu
    assert_true(str_contains($h['content-security-policy'] ?? '', "frame-ancestors 'none'"));
}

function test_p01_t26_http_vuot_tan_suat_tra_429(): void
{
    [$uid, $csrf] = bw_gw_san_sang('t26');
    db_test()->prepare('INSERT INTO gioi_han_tan_suat (khoa, so_lan, het_luc) VALUES (?, 120, NOW() + INTERVAL 1 MINUTE)')->execute(["ajax:$uid"]);
    $r = bw_ping('t26', ['csrf' => $csrf]);
    assert_same([429, 'qua_tan_suat'], [$r['ma'], $r['json']['loi'] ?? null]);
}

function test_p01_s13_chua_dang_nhap_tra_401(): void
{
    bw_auth_lam_sach();
    bw_http_xoa_cookie('kdn');
    $csrf = bw_auth_meta_csrf(bw_http('GET', '/login.php', ['jar' => 'kdn'])['than']); // phiên khách có token hợp lệ
    $r = bw_ping('kdn', ['csrf' => $csrf]);
    assert_same([401, 'chua_dang_nhap'], [$r['ma'], $r['json']['loi'] ?? null]);
}

// ---- P02 S1.5: bản quyền trong gateway (HTTP thật) ----

function test_p02_t31_het_han_goi_phan_tich_tra_402(): void
{
    bw_gp_lam_sach();
    bw_gp_dai_ly('hethan31', bw_gp_ngay(-10));
    bw_gw_vao('t31', 'hethan31');
    $r = bw_ping('t31', ['tep' => '_ping_phan_tich.php']);
    assert_same([402, 'het_han', 402, false], [...bw_gw_ma_loi($r), $r['json']['ma'] ?? null, $r['json']['ok'] ?? null]);
    assert_true(str_contains($r['json']['thong_diep'] ?? '', 'hết hạn'));
    assert_same([402, 'het_han'], bw_gw_ma_loi(bw_ping('t31', ['tep' => '_ping_ghi.php'])), 'Hết hạn: chế độ ghi cũng bị chặn');
    assert_same(200, bw_ping('t31')['ma'], 'Hết hạn vẫn xem được dữ liệu (doc)');
}

function test_p02_t31_tam_khoa_va_chua_co_thue_bao_chan_ca_doc(): void
{
    bw_gp_lam_sach();
    bw_gp_dai_ly('khoa31', bw_gp_ngay(30), 'unlimited', 1);
    bw_gp_dai_ly('chuaco31', null);
    bw_gw_vao('t31k', 'khoa31');
    assert_same([402, 'tam_khoa'], bw_gw_ma_loi(bw_ping('t31k')), 'Tạm khoá: đăng nhập được nhưng không xem được');
    bw_gw_vao('t31c', 'chuaco31');
    assert_same([402, 'chua_co_thue_bao'], bw_gw_ma_loi(bw_ping('t31c')), 'Q-P02-2: chưa có thuê bao → 402');
}

function test_p02_t31_an_han_qua_kem_canh_bao_admin_khong_bi_chan(): void
{
    bw_gp_lam_sach();
    bw_gp_dai_ly('anhan31', bw_gp_ngay(-1));
    bw_auth_tao_user('admin31', ['role' => 'admin']);
    bw_gw_vao('t31a', 'anhan31');
    $r = bw_ping('t31a', ['tep' => '_ping_phan_tich.php']);
    assert_same([200, 'an_han', true], [$r['ma'], $r['json']['trang_thai'] ?? null, $r['json']['canh_bao'] ?? null]);
    bw_gw_vao('t31ad', 'admin31');
    $r = bw_ping('t31ad', ['tep' => '_ping_phan_tich.php']);
    assert_same([200, 'hieu_luc'], [$r['ma'], $r['json']['trang_thai'] ?? null], 'Admin không có thuê bao vẫn dùng được (gói nội bộ)');
}

function test_p02_t25_http_vuot_han_muc_tra_402(): void
{
    bw_gp_lam_sach();
    bw_gp_goi('goi_3', ['so_tin_ngay_toi_da' => 3]);
    $uid = bw_gp_dai_ly('hanmuc25', bw_gp_ngay(10), 'goi_3');
    bw_gw_vao('t25h', 'hanmuc25');
    $csrf = bw_auth_csrf('t25h');
    $ma = [];
    for ($i = 0; $i < 4; $i++) {
        $ma[] = bw_gw_ma_loi(bw_ping('t25h', ['tep' => '_ping_ghi.php', 'csrf' => $csrf, 'json' => ['dem' => true]]));
    }
    assert_same([[200, null], [200, null], [200, null], [402, 'vuot_han_muc']], $ma);
    assert_same(3, bw_gp_da_dung($uid, 'so_tin_doi_soat', bw_gp_ngay()));
}
