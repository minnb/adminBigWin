<?php
declare(strict_types=1);

// P01 S1.2 — Core bảo mật (lib/security/): phiên, CSRF, tiêu đề, rate limit (kế hoạch tổng §5.3).
// Mức đơn vị + HTTP thật qua endpoint chẩn đoán tests/_router.php; S1.3 xác nhận lại qua login/gateway thật.

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_http.php';
require_once __DIR__ . '/../lib/security/PhienBaoMat.php';
require_once __DIR__ . '/../lib/security/Csrf.php';
require_once __DIR__ . '/../lib/security/TieuDeBaoMat.php';
require_once __DIR__ . '/../lib/security/GioiHanTanSuat.php';

/** Một dòng sổ phiên giả cho kiemTra(): mốc thời gian tính bằng giây trước $bayGio. */
function bw_sec_dong(int $bayGio, int $taoTruoc, int $hoatDongTruoc, string $ua = 'UA-A', ?string $thuHoi = null): array
{
    return [
        'tao_luc' => date('Y-m-d H:i:s', $bayGio - $taoTruoc),
        'hoat_dong_luc' => date('Y-m-d H:i:s', $bayGio - $hoatDongTruoc),
        'ua_hash' => PhienBaoMat::bamUa($ua),
        'thu_hoi_luc' => $thuHoi,
    ];
}

function bw_sec_tao_user(): int
{
    bw_test_don_bang('phien_dang_nhap', 'users');
    db_test()->exec("INSERT INTO users (username, password_hash, created_at) VALUES ('dl_sec', 'x', NOW())");
    return (int) db_test()->lastInsertId();
}

// ── T09: cờ cookie & ini phiên ──

function test_p01_t09_cookie_phien_du_co_bao_mat(): void
{
    assert_same(['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'],
        PhienBaoMat::tuyChonCookie(false));
    assert_same(true, PhienBaoMat::tuyChonCookie(true)['secure'], 'HTTPS → cookie phải có Secure');
}

function test_p01_t09_ini_phien_nghiem_ngat(): void
{
    $ini = PhienBaoMat::iniBatBuoc();
    foreach (['session.use_strict_mode' => '1', 'session.use_only_cookies' => '1', 'session.use_trans_sid' => '0',
              'session.cookie_lifetime' => '0', 'session.sid_length' => '48', 'session.sid_bits_per_character' => '6'] as $k => $v) {
        assert_same($v, $ini[$k] ?? null, "ini $k");
    }
}

function test_p01_t09_set_cookie_that_qua_http_du_co(): void
{
    bw_http_xoa_cookie('t09');
    $r = bw_http('GET', '/__bw_test__/phien/bat-dau', ['jar' => 't09']);
    $c = $r['header']['set-cookie'] ?? '';
    assert_true(str_starts_with($c, PhienBaoMat::TEN . '='), "Phải cấp cookie " . PhienBaoMat::TEN . ", nhận: $c");
    foreach (['HttpOnly', 'SameSite=Lax', 'path=/'] as $co) {
        assert_true(stripos($c, $co) !== false, "Set-Cookie thiếu $co: $c");
    }
    assert_true(stripos($c, 'secure') === false, 'Server test chạy HTTP, cookie_secure không bật → không có Secure');
    assert_true(stripos($c, 'expires') === false, 'Cookie phiên không được có hạn cố định (lifetime 0)');
}

// ── T10: chống session fixation ──

function test_p01_t10_dang_nhap_doi_session_id_va_ghi_so_phien(): void
{
    $uid = bw_sec_tao_user();
    bw_http_xoa_cookie('t10');
    $truoc = bw_http('GET', '/__bw_test__/phien/bat-dau', ['jar' => 't10'])['json']['sid'];
    $sau = bw_http('GET', "/__bw_test__/phien/dang-nhap?uid=$uid", ['jar' => 't10'])['json']['sid'];
    assert_true($truoc !== $sau, 'Đăng nhập phải cấp session ID mới');
    $st = db_test()->prepare('SELECT user_id FROM phien_dang_nhap WHERE id_hash = ?');
    $st->execute([PhienBaoMat::bamId($sau)]);
    assert_same($uid, (int) $st->fetchColumn(), 'Sổ phiên phải có dòng cho session ID mới');
    $st->execute([PhienBaoMat::bamId($truoc)]);
    assert_same(false, $st->fetchColumn(), 'Session ID cũ không được ghi vào sổ phiên');
    assert_same($sau, bw_http('GET', '/__bw_test__/phien/bat-dau', ['jar' => 't10'])['json']['sid'], 'Request sau giữ phiên mới');
}

function test_p01_t10_strict_mode_tu_choi_session_id_do_ke_tan_cong_chon(): void
{
    bw_http_xoa_cookie('t10b');
    $sidLa = str_repeat('a', 48);
    $r = bw_http('GET', '/__bw_test__/phien/bat-dau', ['jar' => 't10b', 'headers' => ['Cookie' => PhienBaoMat::TEN . "=$sidLa"]]);
    assert_true($r['json']['sid'] !== $sidLa, 'use_strict_mode: server không được nhận session ID lạ do client tự đặt');
}

// ── T17, T18, T19: hết hạn & User-Agent (hàm thuần trên sổ phiên) ──

function test_p01_t17_het_han_nhan_roi_30_phut(): void
{
    $t = 1_800_000_000;
    assert_same('hop_le', PhienBaoMat::kiemTra(bw_sec_dong($t, 3600, 1799), $t, PhienBaoMat::bamUa('UA-A')));
    assert_same('het_nhan_roi', PhienBaoMat::kiemTra(bw_sec_dong($t, 3600, 1801), $t, PhienBaoMat::bamUa('UA-A')));
}

function test_p01_t18_het_han_tuyet_doi_12_gio(): void
{
    $t = 1_800_000_000;
    assert_same('hop_le', PhienBaoMat::kiemTra(bw_sec_dong($t, 43_199, 5), $t, PhienBaoMat::bamUa('UA-A')));
    assert_same('het_tuyet_doi', PhienBaoMat::kiemTra(bw_sec_dong($t, 43_201, 5), $t, PhienBaoMat::bamUa('UA-A')),
        'Hoạt động liên tục vẫn phải đăng nhập lại sau 12 giờ');
}

function test_p01_t19_doi_user_agent_phai_dang_nhap_lai(): void
{
    $t = 1_800_000_000;
    assert_same('doi_ua', PhienBaoMat::kiemTra(bw_sec_dong($t, 60, 5, 'UA-A'), $t, PhienBaoMat::bamUa('UA-B')));
    assert_same('hop_le', PhienBaoMat::kiemTra(bw_sec_dong($t, 60, 5, 'UA-A'), $t, PhienBaoMat::bamUa('UA-A')));
}

function test_p01_s12_phien_thu_hoi_hoac_khong_co_trong_so(): void
{
    $t = 1_800_000_000;
    assert_same('thu_hoi', PhienBaoMat::kiemTra(bw_sec_dong($t, 60, 5, 'UA-A', date('Y-m-d H:i:s', $t - 1)), $t, PhienBaoMat::bamUa('UA-A')));
    assert_same('khong_co', PhienBaoMat::kiemTra(null, $t, PhienBaoMat::bamUa('UA-A')));
}

// ── CSRF (đơn vị; T20/T21 xác nhận qua gateway ở S1.3) ──

function test_p01_s12_csrf_token_on_dinh_trong_phien_va_xoay_khi_dang_nhap(): void
{
    $_SESSION = [];
    $a = Csrf::token();
    assert_true(preg_match('/^[0-9a-f]{64}$/', $a) === 1, 'Token 32 byte hex');
    assert_same($a, Csrf::token(), 'Cùng phiên → cùng token');
    Csrf::xoay();
    assert_true(Csrf::token() !== $a, 'Xoay (sau đăng nhập) → token mới');
}

function test_p01_s12_csrf_so_khop_hang_so_thoi_gian(): void
{
    $_SESSION = [];
    $tok = Csrf::token();
    assert_true(Csrf::hopLe($tok));
    foreach ([null, '', 'sai', strtoupper($tok)] as $sai) {
        assert_true(!Csrf::hopLe($sai), 'Phải từ chối: ' . var_export($sai, true));
    }
    $_SESSION = [];
    assert_true(!Csrf::hopLe(''), 'Phiên chưa có token → luôn từ chối');
}

function test_p01_s12_csrf_nguon_yeu_cau_cung_origin(): void
{
    $may = ['HTTP_HOST' => 'bigwin.vn'];
    assert_true(Csrf::nguonHopLe($may + ['HTTP_ORIGIN' => 'https://bigwin.vn']));
    assert_true(!Csrf::nguonHopLe($may + ['HTTP_ORIGIN' => 'https://evil.example']));
    assert_true(!Csrf::nguonHopLe($may + ['HTTP_ORIGIN' => 'https://bigwin.vn.evil.example']));
    assert_true(Csrf::nguonHopLe($may + ['HTTP_REFERER' => 'https://bigwin.vn/app/nhap_tin.php']), 'Không có Origin → dùng Referer');
    assert_true(!Csrf::nguonHopLe($may), 'Thiếu cả Origin lẫn Referer → từ chối');
    assert_true(!Csrf::nguonHopLe($may + ['HTTP_ORIGIN' => 'https://bigwin.vn', 'HTTP_SEC_FETCH_SITE' => 'cross-site']));
    assert_true(Csrf::nguonHopLe($may + ['HTTP_ORIGIN' => 'https://bigwin.vn', 'HTTP_SEC_FETCH_SITE' => 'same-origin']));
}

// ── T25: tiêu đề bảo mật ──

function test_p01_t25_tieu_de_bao_mat_day_du(): void
{
    $h = TieuDeBaoMat::danhSach(false, true);
    assert_same('nosniff', $h['X-Content-Type-Options'] ?? null);
    assert_same('same-origin', $h['Referrer-Policy'] ?? null);
    assert_same('no-store', $h['Cache-Control'] ?? null, 'JSON không được cache');
    foreach (["default-src 'self'", "frame-ancestors 'none'", "object-src 'none'", "base-uri 'self'", "form-action 'self'"] as $chiThi) {
        assert_true(str_contains($h['Content-Security-Policy'] ?? '', $chiThi), "CSP thiếu: $chiThi");
    }
    assert_true(!isset($h['Strict-Transport-Security']), 'HTTP thường → không gửi HSTS');
    $https = TieuDeBaoMat::danhSach(true, false);
    assert_true(str_starts_with($https['Strict-Transport-Security'] ?? '', 'max-age='), 'HTTPS → có HSTS');
    assert_true(!isset($https['Cache-Control']), 'Trang HTML không ép no-store');
}

function test_p01_t25_csp_che_do_chat_khong_unsafe(): void
{
    $chat = TieuDeBaoMat::csp(true);
    assert_true(!str_contains($chat, 'unsafe-eval') && !str_contains($chat, 'unsafe-inline'), "CSP chặt (S9.3) không được có unsafe-*: $chat");
    assert_true(str_contains(TieuDeBaoMat::csp(false), 'https://cdn.tailwindcss.com'), 'CSP dev cho phép CDN đã khoá phiên bản (N13)');
}

function test_p01_t25_tieu_de_gui_that_qua_http(): void
{
    $r = bw_http('GET', '/__bw_test__/tieu-de-bao-mat');
    assert_same('nosniff', $r['header']['x-content-type-options'] ?? null);
    assert_same('same-origin', $r['header']['referrer-policy'] ?? null);
    assert_same('no-store', $r['header']['cache-control'] ?? null);
    assert_true(str_contains($r['header']['content-security-policy'] ?? '', "frame-ancestors 'none'"));
}

// ── T26: rate limit (bảng gioi_han_tan_suat — máy dev không có APCu) ──

function test_p01_t26_rate_limit_chan_sau_nguong(): void
{
    bw_test_don_bang('gioi_han_tan_suat');
    foreach ([true, true, true, false, false] as $i => $mongDoi) {
        assert_same($mongDoi, GioiHanTanSuat::cho('dang_nhap:1.2.3.4', 3, 60), 'Lần ' . ($i + 1));
    }
    assert_true(GioiHanTanSuat::cho('dang_nhap:5.6.7.8', 3, 60), 'Khoá khác đếm riêng');
}

function test_p01_t26_rate_limit_het_cua_so_thi_dem_lai(): void
{
    bw_test_don_bang('gioi_han_tan_suat');
    for ($i = 0; $i < 4; $i++) {
        GioiHanTanSuat::cho('ajax:42', 3, 60);
    }
    assert_true(!GioiHanTanSuat::cho('ajax:42', 3, 60));
    db_test()->exec('UPDATE gioi_han_tan_suat SET het_luc = NOW() - INTERVAL 1 SECOND');
    assert_true(GioiHanTanSuat::cho('ajax:42', 3, 60), 'Hết cửa sổ → đếm lại từ 1');
    assert_same(1, (int) db_test()->query('SELECT so_lan FROM gioi_han_tan_suat')->fetchColumn());
}

function test_p01_t26_khoa_dai_duoc_rut_gon_van_dem_dung(): void
{
    bw_test_don_bang('gioi_han_tan_suat');
    $dai = 'dang_nhap:' . str_repeat('x', 300);
    assert_true(GioiHanTanSuat::cho($dai, 1, 60));
    assert_true(!GioiHanTanSuat::cho($dai, 1, 60));
    assert_true(strlen((string) db_test()->query('SELECT khoa FROM gioi_han_tan_suat')->fetchColumn()) <= 120);
}

function test_p01_s12_rate_limit_xoa_dem_sau_dang_nhap_dung(): void
{
    bw_test_don_bang('gioi_han_tan_suat');
    GioiHanTanSuat::cho('dang_nhap:u1', 1, 60);
    assert_true(!GioiHanTanSuat::cho('dang_nhap:u1', 1, 60));
    GioiHanTanSuat::datLai('dang_nhap:u1');
    assert_true(GioiHanTanSuat::cho('dang_nhap:u1', 1, 60));
}
