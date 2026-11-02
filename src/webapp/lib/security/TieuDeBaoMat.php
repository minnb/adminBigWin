<?php
declare(strict_types=1);

// Tiêu đề bảo mật cho mọi trang/endpoint web BigWIN (kế hoạch tổng §5.3d, N13).
// CSP dev: cho phép CDN đã khoá phiên bản (Tailwind CDN cần inline/eval, Alpine bản thường cần eval).
// CSP chặt (cfg('csp_chat') = true): bật ở S9.3 khi chuyển sang CSS build sẵn + Alpine CSP build.

require_once __DIR__ . '/../bootstrap.php';

final class TieuDeBaoMat
{
    public static function csp(bool $chat): string
    {
        $chung = "default-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:";
        if ($chat) {
            return "$chung; script-src 'self'; style-src 'self'; font-src 'self'";
        }
        return "$chung; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net"
            . "; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com"
            . "; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com";
    }

    /** Tên → giá trị. HSTS chỉ khi HTTPS; JSON thì cấm cache. */
    public static function danhSach(bool $https, bool $json): array
    {
        $h = [
            'Content-Security-Policy' => self::csp(cfg('csp_chat') === true),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
        ];
        if ($https) {
            $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if ($json) {
            $h['Cache-Control'] = 'no-store';
        }
        return $h;
    }

    public static function gui(bool $json = false): void
    {
        foreach (self::danhSach(la_https(), $json) as $ten => $giaTri) {
            header("$ten: $giaTri");
        }
    }
}
