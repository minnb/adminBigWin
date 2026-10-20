<?php
declare(strict_types=1);

// Dùng chung cho mọi file api/*.php. Không truy cập trực tiếp (chặn bằng .htaccess).
// Thiết kế: D:\Projects\bigwin\docs\copyright\BACKUP_DU_LIEU_CLOUD.md §6.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/crypto.php';  // ma_hoa / giai_ma — lỗi khoá ném exception → endpoint trả 500

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// `never`: nếu `exit` bị vô hiệu (uopz trên hosting) PHP ném TypeError thay vì chạy tiếp
// xuống INSERT — lớp chặn thứ 2 sau uopz_allow_exit() ở bootstrap.php.
function fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

/**
 * Lỗi phía server → 500 + {"error":"server","ma":…,"ref":…}. `ma` (vd db_thieu_bang) cho biết hướng
 * sửa mà không lộ chi tiết; nguyên văn lỗi nằm trong error_log, tìm theo `ref`.
 */
function loi_may_chu(Throwable $e): never
{
    $ref = ghi_loi($e);
    http_response_code(500);
    echo json_encode(['error' => 'server', 'ma' => chan_doan_loi($e)['ma'], 'ref' => $ref]);
    exit;
}

// Lỗi lọt ngoài try/catch của endpoint (vd thiếu config.php) vẫn trả JSON, không phải trang 500 trắng.
set_exception_handler('loi_may_chu');

function ok(array $data): never
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function str_field(array $in, string $key, int $max): string
{
    $v = $in[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function la_hex($v, int $len): bool
{
    return is_string($v) && strlen($v) === $len && ctype_xdigit($v) && strtolower($v) === $v;
}

/** POST JSON → array. Sai method / quá lớn / không phải JSON object → fail. */
function doc_json(int $toiDaByte): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        fail(405, 'method');
    }
    $raw = file_get_contents('php://input', false, null, 0, $toiDaByte + 1);
    if ($raw !== false && strlen($raw) > $toiDaByte) {
        fail(413, 'too_large');
    }
    $in = json_decode((string)$raw, true);
    if (!is_array($in)) {
        fail(400, 'json');
    }
    return $in;
}

/** Đếm request theo IP trên check_logs (chung với check.php). Vượt → 429. */
function gioi_han_tan_suat(PDO $pdo, string $ip): void
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM check_logs WHERE ip = ? AND created_at > NOW() - INTERVAL 1 MINUTE');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= (int)cfg('rate_limit_per_min')) {
        fail(429, 'rate');
    }
}

function ghi_log(PDO $pdo, string $machineId, string $ip, string $ketQua): void
{
    $pdo->prepare('INSERT INTO check_logs (machine_id, ip, result, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$machineId, $ip, $ketQua]);
}

/**
 * Dọn check_logs quá `log_luu_ngay` ngày (mặc định 90) — bảng chỉ dùng cho rate limit (1 phút gần
 * nhất) + tra cứu, không dọn thì ~1,5 triệu dòng/năm ở 1.000 máy. Chạy NGẪU NHIÊN 1/`$moiN` request
 * và tối đa `$toiDa` dòng/lần để không chặn request nào lâu. Cần index idx_created (migration 2026-10-20).
 */
function don_log_cu(PDO $pdo, int $moiN = 200, int $toiDa = 10000): int
{
    if (random_int(1, $moiN) !== 1) {
        return 0;
    }
    $st = $pdo->prepare('DELETE FROM check_logs WHERE created_at < NOW() - INTERVAL ? DAY LIMIT ?');
    $st->bindValue(1, (int)(cfg('log_luu_ngay') ?? 90), PDO::PARAM_INT);
    $st->bindValue(2, $toiDa, PDO::PARAM_INT);
    $st->execute();
    return $st->rowCount();
}

/**
 * Xác thực máy bằng machine_id + device_secret (D4). Trả dòng `machines`.
 * - Sai định dạng / chưa đăng ký khoá / khoá sai → 401 (khoá sai thì đánh dấu secret_mismatch_at).
 * - Máy không có / bị thu hồi / trạng thái không được phép → 403.
 *
 * @param string[] $trangThaiChoPhep
 */
function xac_thuc_may(PDO $pdo, array $in, array $trangThaiChoPhep): array
{
    $mid = $in['machine_id'] ?? '';
    $secret = $in['device_secret'] ?? '';
    if (!la_hex($mid, 64) || !la_hex($secret, 64)) {
        fail(401, 'auth');
    }
    $st = $pdo->prepare('SELECT * FROM machines WHERE machine_id = ?');
    $st->execute([$mid]);
    $may = $st->fetch();
    if (!$may) {
        fail(403, 'unknown_machine');
    }
    if ($may['device_secret_hash'] === null) {
        fail(401, 'secret_not_registered');
    }
    if (!hash_equals($may['device_secret_hash'], hash('sha256', $secret))) {
        $pdo->prepare('UPDATE machines SET secret_mismatch_at = NOW() WHERE id = ?')->execute([$may['id']]);
        fail(401, 'auth');
    }
    // 'pending' quá hạn dùng thử thì KHÔNG được dùng backup; trong hạn được coi là 'trial'.
    $trangThai = trang_thai_hieu_luc($may['status'], (int)strtotime((string)$may['first_seen']), time());
    if (!in_array($trangThai, $trangThaiChoPhep, true)) {
        fail(403, 'status');
    }
    return $may;
}

/** Máy hiện tại được xem phiên bản của máy nào: chính nó + máy cùng owner_id (D6). */
function dieu_kien_quyen_xem(array $may): array
{
    if ($may['owner_id'] === null) {
        return ['v.machine_id = ?', [$may['machine_id']]];
    }
    return ['(v.machine_id = ? OR m.owner_id = ?)', [$may['machine_id'], (int)$may['owner_id']]];
}
