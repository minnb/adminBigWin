<?php
declare(strict_types=1);

/**
 * POST /api/backup_get.php — tải 1 phiên bản để khôi phục.
 *
 * Vào : {machine_id, device_secret, version_id}
 * Ra  : {bo, hash, data = base64(gzip(json))} — app tự kiểm sha256 sau giải nén (R9).
 * Quyền giống backup_list. Mọi lượt tải ghi backup_access_logs.
 */

require __DIR__ . '/../lib/api.php';

$ip = client_ip();
try {
    $pdo = db();
    gioi_han_tan_suat($pdo, $ip);
    $in = doc_json(4096);
    $may = xac_thuc_may($pdo, $in, ['active']);

    $versionId = $in['version_id'] ?? null;
    if (!is_int($versionId) || $versionId <= 0) {
        fail(400, 'input');
    }
    [$dk, $tham] = dieu_kien_quyen_xem($may);
    $st = $pdo->prepare("SELECT v.bo, v.hash, v.du_lieu
                         FROM backup_versions v JOIN machines m ON m.machine_id = v.machine_id
                         WHERE v.id = ? AND $dk");
    $st->execute(array_merge([$versionId], $tham));
    $r = $st->fetch();
    if (!$r) {
        fail(404, 'not_found');  // không có HOẶC không có quyền — không phân biệt để khỏi dò
    }
    $pdo->prepare('INSERT INTO backup_access_logs (machine_id, version_id, ip, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$may['machine_id'], $versionId, $ip]);
    ghi_log($pdo, $may['machine_id'], $ip, 'backup_get');
    ok(['bo' => $r['bo'], 'hash' => $r['hash'], 'data' => base64_encode(giai_ma($r['du_lieu']))]);
} catch (Throwable $e) {
    loi_may_chu($e);
}
