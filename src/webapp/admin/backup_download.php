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
    trang_bao_loi(404, 'Không tìm thấy phiên bản', 'Phiên bản backup này không tồn tại hoặc đã bị dọn (server chỉ giữ 30 bản mới nhất + bản cuối mỗi tháng).');
}
// Lỗi khoá / giải mã ném LoiCauHinh → trang lỗi chung của _auth.php hiện hướng dẫn sửa.
$raw = gzdecode(giai_ma($r['du_lieu']));
if ($raw === false || !hash_equals($r['hash'], hash('sha256', $raw))) {
    trang_bao_loi(500, 'Bản backup hỏng', 'Dữ liệu giải nén xong không khớp mã băm lúc lưu — bản này không dùng được, hãy chọn phiên bản khác.');
}
$ten = preg_replace('/[^A-Za-z0-9_-]/', '_', ($r['hostname'] ?? 'may') . '_' . $r['bo'] . '_' . $r['created_at']);
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $ten . '.json"');
header('X-Content-Type-Options: nosniff');
echo json_encode(json_decode($raw, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
