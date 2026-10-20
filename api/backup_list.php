<?php
declare(strict_types=1);

/**
 * POST /api/backup_list.php — các phiên bản backup máy này được phép khôi phục.
 *
 * Vào : {machine_id, device_secret, bo?}
 * Ra  : {phien_ban: [{version_id, bo, hash, so_dong, kich_thuoc, created_at, hostname, may_minh}]}
 * Quyền: máy phải ACTIVE (R12); thấy phiên bản của chính nó + máy cùng owner_id (D6).
 */

require __DIR__ . '/../lib/api.php';

$ip = client_ip();
try {
    $pdo = db();
    gioi_han_tan_suat($pdo, $ip);
    $in = doc_json(4096);
    $may = xac_thuc_may($pdo, $in, ['active']);

    [$dk, $tham] = dieu_kien_quyen_xem($may);
    $bo = $in['bo'] ?? null;
    if ($bo !== null) {
        if (!in_array($bo, ['cu_phap', 'khach_hang'], true)) {
            fail(400, 'input');
        }
        $dk .= ' AND v.bo = ?';
        $tham[] = $bo;
    }
    $st = $pdo->prepare("SELECT v.id, v.bo, v.hash, v.so_dong_json, v.kich_thuoc, v.created_at,
                                v.machine_id, m.hostname
                         FROM backup_versions v JOIN machines m ON m.machine_id = v.machine_id
                         WHERE $dk ORDER BY v.id DESC LIMIT 200");
    $st->execute($tham);
    $ds = [];
    foreach ($st->fetchAll() as $r) {
        $ds[] = [
            'version_id' => (int)$r['id'], 'bo' => $r['bo'], 'hash' => $r['hash'],
            'so_dong' => json_decode($r['so_dong_json'], true) ?: [],
            'kich_thuoc' => (int)$r['kich_thuoc'], 'created_at' => $r['created_at'],
            'hostname' => $r['hostname'], 'may_minh' => $r['machine_id'] === $may['machine_id'],
        ];
    }
    ghi_log($pdo, $may['machine_id'], $ip, 'backup_list');
    ok(['phien_ban' => $ds]);
} catch (Throwable $e) {
    error_log('BigWIN backup_list: ' . $e->getMessage());
    fail(500, 'server');
}
