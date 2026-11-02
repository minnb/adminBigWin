<?php
declare(strict_types=1);

// CSRF cho web BigWIN (kế hoạch tổng §5.3b): synchronizer token 32 byte/phiên, xoay khi đăng nhập,
// so khớp hash_equals; kiểm thêm nguồn request (Origin/Referer cùng host, Sec-Fetch-Site) cho mọi request đổi dữ liệu.
// Đọc/ghi $_SESSION của phiên đang mở (PhienBaoMat::batDau()).

final class Csrf
{
    private const KHOA = 'csrf';

    public static function token(): string
    {
        return $_SESSION[self::KHOA] ??= bin2hex(random_bytes(32));
    }

    /** Token mới — gọi khi đăng nhập (cùng lúc đổi session ID). */
    public static function xoay(): void
    {
        $_SESSION[self::KHOA] = bin2hex(random_bytes(32));
    }

    public static function hopLe(?string $gui): bool
    {
        $tok = $_SESSION[self::KHOA] ?? '';
        return is_string($tok) && $tok !== '' && is_string($gui) && hash_equals($tok, $gui);
    }

    /** Request đến từ chính trang này: Origin (không có thì Referer) cùng host:port với Host; Sec-Fetch-Site nếu có phải same-origin/none. */
    public static function nguonHopLe(array $server): bool
    {
        $site = $server['HTTP_SEC_FETCH_SITE'] ?? null;
        if ($site !== null && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }
        $nguon = (string) ($server['HTTP_ORIGIN'] ?? $server['HTTP_REFERER'] ?? '');
        $host = parse_url($nguon, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $port = parse_url($nguon, PHP_URL_PORT);
        return hash_equals(strtolower((string) ($server['HTTP_HOST'] ?? '')), strtolower($host . ($port ? ":$port" : '')));
    }
}
