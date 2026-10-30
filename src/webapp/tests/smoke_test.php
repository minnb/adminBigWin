<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/bigwin/helpers.php';

function test_extension_bat_buoc(): void
{
    foreach (['intl', 'mbstring', 'pdo_mysql', 'sodium', 'curl', 'dom', 'json'] as $ext) {
        assert_true(extension_loaded($ext), "Thiếu extension PHP: $ext");
    }
}

function test_php_toi_thieu_8_1(): void
{
    assert_true(PHP_VERSION_ID >= 80100, 'Cần PHP >= 8.1 (enum, readonly, never)');
}

function test_mui_gio_asia_ho_chi_minh(): void
{
    assert_same('Asia/Ho_Chi_Minh', date_default_timezone_get());
    assert_same('+07:00', now_vn()->format('P'));
}

function test_pcre_ho_tro_unicode(): void
{
    assert_same(1, preg_match('/^\p{L}+$/u', 'đềlô'));
    assert_same(1, preg_match('/(*UCP)^\w+$/u', 'Đề'));
}

function test_int_64_bit(): void
{
    assert_same(8, PHP_INT_SIZE, 'Tiền VNĐ cần số nguyên 64-bit');
}
