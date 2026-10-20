<?php
declare(strict_types=1);

// Dùng chung cho api/ và admin/. Không truy cập trực tiếp (chặn bằng .htaccess).

date_default_timezone_set('Asia/Ho_Chi_Minh');

function cfg(string $key)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/../config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Missing config.php');
        }
        $cfg = require $file;
    }
    return $cfg[$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(cfg('db_dsn'), cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Giờ Việt Nam cho NOW() — thống nhất với app (UTC+7).
        $pdo->exec("SET time_zone = '+07:00'");
        // Strict: giá trị vượt cột / sai kiểu phải BÁO LỖI, không được cắt im lặng (XAMPP mặc định
        // không strict — đo 2026-10-19: 99999999999 vào cột INT bị cắt về 2147483647, không lỗi).
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }
    return $pdo;
}

/**
 * Trạng thái HIỆU LỰC của máy — chỗ DUY NHẤT định nghĩa "dùng thử": máy 'pending' còn trong
 * `trial_ttl` kể từ lần đầu API lưu máy (first_seen) thì là 'trial' (được dùng). Hết hạn vẫn
 * 'pending' (app chặn). active / revoked giữ nguyên. Dùng cho check.php + xác thực API backup.
 */
function trang_thai_hieu_luc(string $status, int $firstSeenTs, int $now): string
{
    return ($status === 'pending' && $now < het_han_dung_thu($firstSeenTs)) ? 'trial' : $status;
}

/** Epoch hết hạn dùng thử của máy — cùng nguồn với `trang_thai_hieu_luc` (token, trang admin). */
function het_han_dung_thu(int $firstSeenTs): int
{
    return $firstSeenTs + (int)cfg('trial_ttl');
}

/** Mẫu `LIKE %q%` an toàn: thoát `%` `_` `\` để người tìm "50%" không khớp mọi thứ. */
function mau_like(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}

/** Trường text của form POST: trim + cắt theo độ dài cột (strict mode báo lỗi thay vì tự cắt). */
function post_text(string $key, int $max): string
{
    return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max);
}

function client_ip(): string
{
    // CHỈ tin REMOTE_ADDR. X-Forwarded-For do client tự đặt được → lách rate limit.
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
