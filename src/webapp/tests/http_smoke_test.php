<?php
declare(strict_types=1);

// P01 S1.0 — hạ tầng test HTTP: `php -S` chạy mã thật trên DB test, client có cookie jar.
// (P03: /admin/login.php chuyển hướng về /login.php chung → smoke dùng /login.php, cookie phiên `bigwin_web`.)

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_http.php';

function test_p01_t03_may_chu_test_dung_cau_hinh_db_test(): void
{
    $r = bw_http('GET', '/__bw_test__/moi-truong');
    assert_same(200, $r['ma']);
    assert_same(['moi_truong' => 'test', 'db' => bw_ten_db_test((string) cfg('db_dsn'))], $r['json']);
}

function test_p01_t03_trang_dang_nhap_tra_200_co_csrf(): void
{
    bw_http_xoa_cookie();
    $r = bw_http('GET', '/login.php');
    assert_same(200, $r['ma'], 'Trang đăng nhập phải chạy được trên DB test');
    assert_true(str_contains($r['than'], 'name="csrf"'), 'Trang đăng nhập phải có trường CSRF');
}

function test_p01_t03_cookie_jar_giu_phien_giua_cac_request(): void
{
    bw_http_xoa_cookie();
    $dau = bw_http('GET', '/login.php');
    assert_true(isset($dau['cookie']['bigwin_web']), 'Lần đầu server phải cấp cookie phiên');
    $sau = bw_http('GET', '/login.php');
    assert_true(!isset($sau['cookie']['bigwin_web']), 'Lần sau client phải gửi lại cookie cũ (server không cấp phiên mới)');
}

function test_p01_t03_post_json_gan_hoac_bo_csrf_origin(): void
{
    $r = http_post_json('/__bw_test__/tieu-de', ['a' => 1], ['csrf' => 'tok123']);
    assert_same('tok123', $r['json']['csrf'] ?? null);
    assert_same(bw_http_goc(), $r['json']['origin'] ?? null, 'Mặc định gửi Origin cùng host');
    assert_same('application/json', $r['json']['content_type'] ?? null);
    $r = bw_http('POST', '/__bw_test__/tieu-de', ['json' => [], 'origin' => false]);
    assert_same(null, $r['json']['csrf'] ?? null);
    assert_same(null, $r['json']['origin'] ?? null);
    $r = bw_http('POST', '/__bw_test__/tieu-de', ['json' => [], 'origin' => 'https://khac.example']);
    assert_same('https://khac.example', $r['json']['origin'] ?? null);
}
