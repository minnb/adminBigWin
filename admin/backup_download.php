<?php
declare(strict_types=1);

// Admin tải 1 bản backup dạng JSON (đã giải mã + giải nén) để xem / lưu giữ ngoài server.

require __DIR__ . '/_auth.php';
require __DIR__ . '/../lib/crypto.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT v.bo, v.hash, v.du_lieu, v.created_at, m.hostname
                     FROM backup_versions v LEFT JOIN machines m ON m.machine_id = v.machine_id WHERE v.id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    exit('Không tìm thấy phiên bản');
}
try {
    $raw = gzdecode(giai_ma($r['du_lieu']));
} catch (Throwable $e) {
    error_log('BigWIN backup_download: ' . $e->getMessage());
    http_response_code(500);
    exit('Không giải mã được bản backup (kiểm tra file khoá backup_key_file)');
}
if ($raw === false || !hash_equals($r['hash'], hash('sha256', $raw))) {
    http_response_code(500);
    exit('Bản backup hỏng (mã băm không khớp)');
}
$ten = preg_replace('/[^A-Za-z0-9_-]/', '_', ($r['hostname'] ?? 'may') . '_' . $r['bo'] . '_' . $r['created_at']);
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $ten . '.json"');
header('X-Content-Type-Options: nosniff');
echo json_encode(json_decode($raw, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
