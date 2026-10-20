<?php
declare(strict_types=1);

/**
 * POST /api/check.php — BigWIN gọi lúc khởi động + mỗi 6 giờ.
 *
 * Vào : JSON {machine_id, hostname, app_version, nonce, tg_id?, tg_username?, tg_name?}
 * Ra  : JSON {payload: base64(JSON), sig: base64(Ed25519(payload_bytes))}
 *       payload = {machine_id, status: active|trial|pending|revoked, nonce, issued_at, valid_until}
 *
 * Máy chưa có trong DB → tự tạo 'pending', admin duyệt trên /admin/.
 * 'pending' trong `trial_ttl` đầu tiên (tính từ first_seen) được trả là 'trial' = vẫn dùng được.
 * Lỗi → HTTP 4xx/5xx + {"error": "..."} ngắn gọn, KHÔNG lộ chi tiết.
 */

require __DIR__ . '/../lib/api.php';

/**
 * Đăng ký khoá máy cho cloud backup theo kiểu tin lần đầu (D5). Khoá sai CHỈ đánh dấu
 * `secret_mismatch_at` — KHÔNG ảnh hưởng bản quyền (R11). Admin "Đặt lại khoá" để đăng ký lại.
 */
function dang_ky_khoa_may(PDO $pdo, string $machineId, $secret): void
{
    if (!la_hex($secret, 64)) {
        return;
    }
    $hash = hash('sha256', $secret);
    $pdo->prepare('UPDATE machines SET device_secret_hash = ? WHERE machine_id = ? AND device_secret_hash IS NULL')
        ->execute([$hash, $machineId]);
    $pdo->prepare('UPDATE machines SET secret_mismatch_at = NOW()
                   WHERE machine_id = ? AND device_secret_hash IS NOT NULL AND device_secret_hash <> ?')
        ->execute([$machineId, $hash]);
}

/**
 * Trạng thái trả cho app + hạn token. Dùng thử: máy chưa duyệt vẫn được dùng tới
 * first_seen + trial_ttl, để khách đang dùng không bị chặn trong lúc admin chưa kịp duyệt.
 * Hết hạn → 'pending' → app chặn. Token dùng thử không được sống quá mốc hết thử.
 *
 * @return array{0: string, 1: int} [status, valid_until]
 */
function tinh_trang_thai(string $status, int $firstSeenTs, int $now): array
{
    $hieuLuc = trang_thai_hieu_luc($status, $firstSeenTs, $now);
    $han = $now + (int)cfg('offline_ttl');
    if ($hieuLuc === 'trial') {
        $han = min($han, het_han_dung_thu($firstSeenTs));
    }
    return [$hieuLuc, $han];
}

// Kiểm method TRƯỚC khi mở DB (doc_json cũng kiểm, nhưng sau rate-limit): GET trả 405 ngay, không tốn truy vấn.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'method');
}

$ip = client_ip();

try {
    $pdo = db();
    gioi_han_tan_suat($pdo, $ip);
    $in = doc_json(8192);

    $machineId = $in['machine_id'] ?? '';
    $nonce = $in['nonce'] ?? '';
    // la_hex: đúng độ dài + chỉ hex thường. Chặt hơn regex `$` cũ (regex nhận thêm 1 "\n" ở cuối).
    $hostname = str_field($in, 'hostname', 100);
    // App thật: machine_id = sha256 (không thể toàn số 0) và luôn gửi tên máy → thiếu = giả, KHÔNG đăng ký.
    if (!la_hex($machineId, 64) || !la_hex($nonce, 32)
        || $machineId === str_repeat('0', 64) || $hostname === '') {
        ghi_log($pdo, '', $ip, 'bad_input');
        fail(400, 'input');
    }

    $appVersion = str_field($in, 'app_version', 20);
    $tgUsername = str_field($in, 'tg_username', 64);
    $tgName = str_field($in, 'tg_name', 128);
    $tgId = $in['tg_id'] ?? null;
    $tgId = (is_int($tgId) && $tgId > 0) ? $tgId : null;

    // Máy mới → pending. Máy cũ → cập nhật thông tin nhận diện, GIỮ NGUYÊN status.
    // tg_* chỉ ghi đè khi client gửi tg_id (chưa đăng nhập Telegram thì giữ giá trị cũ).
    $pdo->prepare(
        'INSERT INTO machines
            (machine_id, hostname, app_version, last_ip, tg_id, tg_username, tg_name,
             tg_updated_at, first_seen, last_seen)
         VALUES (?, ?, ?, ?, ?, ?, ?, IF(? IS NULL, NULL, NOW()), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            hostname      = VALUES(hostname),
            app_version   = VALUES(app_version),
            last_ip       = VALUES(last_ip),
            last_seen     = NOW(),
            tg_username   = IF(VALUES(tg_id) IS NULL, tg_username, VALUES(tg_username)),
            tg_name       = IF(VALUES(tg_id) IS NULL, tg_name, VALUES(tg_name)),
            tg_updated_at = IF(VALUES(tg_id) IS NULL, tg_updated_at, NOW()),
            tg_id         = IF(VALUES(tg_id) IS NULL, tg_id, VALUES(tg_id))'
    )->execute([$machineId, $hostname, $appVersion, $ip, $tgId, $tgUsername, $tgName, $tgId]);

    dang_ky_khoa_may($pdo, $machineId, $in['device_secret'] ?? '');

    // UNIX_TIMESTAMP đọc first_seen theo session time_zone (+07:00) → epoch đúng.
    $st = $pdo->prepare('SELECT status, UNIX_TIMESTAMP(first_seen) AS first_seen_ts FROM machines WHERE machine_id = ?');
    $st->execute([$machineId]);
    $row = $st->fetch();
    $now = time();
    [$status, $validUntil] = tinh_trang_thai((string)$row['status'], (int)$row['first_seen_ts'], $now);

    ghi_log($pdo, $machineId, $ip, $status);
    // Việc PHỤ: dọn log lỗi (khoá bảng lâu…) KHÔNG được làm hỏng lượt kiểm tra bản quyền.
    try {
        don_log_cu($pdo);
    } catch (Throwable $e) {
        error_log('BigWIN don_log_cu: ' . $e->getMessage());
    }

    $sk = @file_get_contents((string)cfg('secret_key_file'));
    if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new LoiCauHinh('khoa_ky', 'Không đọc được file khoá ký (secret_key_file trong config.php): sai đường dẫn, thiếu quyền đọc hoặc file không đủ 64 byte. Dùng ĐÚNG file license_secret.key cũ — sinh khoá mới thì mọi app đã phát hành sẽ từ chối server.');
    }

    $payload = json_encode([
        'machine_id'  => $machineId,
        'status'      => $status,
        'nonce'       => $nonce,
        'issued_at'   => $now,
        'valid_until' => $validUntil,
    ], JSON_UNESCAPED_SLASHES);
    $sig = sodium_crypto_sign_detached($payload, $sk);
    sodium_memzero($sk);

    echo json_encode([
        'payload' => base64_encode($payload),
        'sig'     => base64_encode($sig),
    ]);
} catch (Throwable $e) {
    loi_may_chu($e);
}
