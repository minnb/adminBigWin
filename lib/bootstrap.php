<?php
declare(strict_types=1);

// Dùng chung cho api/ và admin/. Không truy cập trực tiếp (chặn bằng .htaccess).

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Hosting bật extension uopz (uopz.exit=0) → `exit` KHÔNG dừng script: fail() in lỗi rồi chạy
// tiếp, trả nhiều JSON nối nhau + 500. Mọi lối thoát của api/admin dựa vào exit → bật lại.
if (function_exists('uopz_allow_exit')) {
    uopz_allow_exit(true);
}

/** Lỗi do CẤU HÌNH server (thiếu config, sai khoá…) — mang sẵn mã + hướng dẫn sửa cho admin. */
final class LoiCauHinh extends RuntimeException
{
    public function __construct(public readonly string $ma, string $goiY)
    {
        parent::__construct($goiY);
    }
}

function cfg(string $key)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/../config.php';
        if (!is_file($file)) {
            throw new LoiCauHinh('thieu_config', 'Thiếu file config.php — copy config.sample.php thành config.php rồi điền thông tin database và đường dẫn 2 file khoá.');
        }
        $cfg = require $file;
        if (!is_array($cfg)) {
            throw new LoiCauHinh('config_sai', 'config.php phải `return [ ... ];` một mảng cấu hình (xem config.sample.php).');
        }
    }
    return $cfg[$key] ?? null;
}

/**
 * Chẩn đoán lỗi → ['ma' => mã ngắn, 'goi_y' => việc admin cần làm]. CHỈ chứa tên bảng/cột/mã lỗi,
 * KHÔNG chứa mật khẩu, user DB hay đường dẫn file khoá → an toàn để hiện cả ở trang đăng nhập.
 * Thêm loại lỗi mới = thêm 1 dòng vào bảng $theoMaMysql.
 */
function chan_doan_loi(Throwable $e): array
{
    if ($e instanceof LoiCauHinh) {
        return ['ma' => $e->ma, 'goi_y' => $e->getMessage()];
    }
    // config.php hỏng thì KHÔNG đăng nhập / bật debug được → phải tự nói dòng lỗi (số dòng không nhạy cảm).
    if ($e instanceof ParseError && basename($e->getFile()) === 'config.php') {
        return ['ma' => 'config_sai_cu_phap', 'goi_y' => 'config.php sai cú pháp ở dòng ' . $e->getLine() . ' (thường do thiếu dấu phẩy , hoặc nháy \' ở dòng cấu hình NGAY PHÍA TRÊN dòng này, bỏ qua các dòng chú thích //) — so với config.sample.php.'];
    }
    if (!$e instanceof PDOException) {
        return ['ma' => 'loi_code', 'goi_y' => 'Lỗi trong mã nguồn PHP — xem chi tiết (đăng nhập admin hoặc bật \'debug\' => true trong config.php) và file error_log.'];
    }
    $msg = $e->getMessage();
    // Lỗi lúc kết nối không có errorInfo → lấy mã MySQL trong message ("SQLSTATE[HY000] [1045] ...").
    $maMysql = (int)($e->errorInfo[1] ?? 0);
    if ($maMysql === 0 && preg_match('/\[(\d{4})\]/', $msg, $m)) {
        $maMysql = (int)$m[1];
    }
    $ten = preg_match("/'(?:[^'.]+\\.)?([A-Za-z0-9_]+)'/", $msg, $m) ? $m[1] : '?';
    $theoMaMysql = [
        2002 => ['db_ket_noi', 'Không kết nối được MySQL — kiểm tra host trong db_dsn của config.php (cPanel dùng host=localhost).'],
        2005 => ['db_ket_noi', 'Không tìm thấy máy chủ MySQL — kiểm tra host trong db_dsn của config.php.'],
        2006 => ['db_ket_noi', 'Mất kết nối MySQL — thử lại; lặp lại thì liên hệ nhà cung cấp hosting.'],
        1045 => ['db_sai_tai_khoan', 'Sai db_user hoặc db_pass trong config.php (cPanel → MySQL Databases để xem/đặt lại mật khẩu user).'],
        1044 => ['db_khong_quyen', 'User database chưa được gán quyền — cPanel → MySQL Databases → Add User To Database, chọn ALL PRIVILEGES.'],
        1049 => ['db_khong_ton_tai', 'Database trong db_dsn không tồn tại — cPanel thêm tiền tố tài khoản vào tên (vd: abc_bigwin), sửa dbname cho đúng.'],
        1146 => ['db_thieu_bang', "Thiếu bảng `$ten` — import schema.sql vào database (phpMyAdmin → Import)."],
        1054 => ['db_thieu_cot', "Thiếu cột `$ten` — database tạo từ bản cũ: chạy các file migrations/*.sql theo thứ tự ngày."],
        1142 => ['db_khong_quyen', 'User database thiếu quyền thao tác bảng — gán ALL PRIVILEGES cho user trong cPanel.'],
    ];
    [$ma, $goiY] = $theoMaMysql[$maMysql] ?? ['db_loi', "Lỗi database (mã MySQL $maMysql) — xem chi tiết hoặc file error_log."];
    return ['ma' => $ma, 'goi_y' => $goiY];
}

/** Ghi lỗi đầy đủ vào error_log kèm mã tra cứu; người dùng chỉ thấy mã này để đối chiếu. */
function ghi_loi(Throwable $e): string
{
    $ref = bin2hex(random_bytes(4));
    error_log(sprintf('BigWIN [%s] %s: %s @ %s:%d', $ref, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    return $ref;
}

/** Hiện nguyên văn lỗi (message, file:line)? Chỉ khi config bật 'debug' — config hỏng thì coi là tắt. */
function che_do_debug(): bool
{
    try {
        return (bool)cfg('debug');
    } catch (Throwable) {
        return false;
    }
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
