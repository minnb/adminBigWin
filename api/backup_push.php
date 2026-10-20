<?php
declare(strict_types=1);

/**
 * POST /api/backup_push.php — app gửi 1 bản chụp bộ dữ liệu (cu_phap | khach_hang).
 *
 * Vào : {machine_id, device_secret, bo, hash, so_dong{}, app_version, data = base64(gzip(json))}
 * Ra  : {ket_qua: "da_luu" | "da_co", version_id}
 * Trùng hash với phiên bản MỚI NHẤT của máy → "da_co", không lưu thêm (nhịp xác nhận 24h, R3).
 * Lưu mã hoá secretbox (dữ liệu khách cược). Máy đã duyệt: 30 bản mới nhất + bản cuối mỗi tháng (Q4);
 * máy dùng thử: 5 bản mới nhất. Trần 512 KB nén.
 */

require __DIR__ . '/../lib/api.php';

const NEN_TOI_DA = 512 * 1024;             // R13: trước giải nén (dữ liệu thật ~20 KB nén)
const GIAI_NEN_TOI_DA = 10 * 1024 * 1024;  // R13: sau giải nén
const GIU_MOI_NHAT = 30;
const GIU_MOI_NHAT_DUNG_THU = 5;  // máy chưa duyệt: ai cũng tạo được máy giả → giới hạn dung lượng

/** Giữ $giu bản mới nhất + bản cuối cùng của mỗi tháng (máy dùng thử: CHỈ $giu bản); xoá phần còn lại. */
function don_phien_ban_cu(PDO $pdo, string $mid, string $bo, int $giu, bool $giuBanThang): void
{
    $st = $pdo->prepare("SELECT id, DATE_FORMAT(created_at, '%Y-%m') AS thang FROM backup_versions
                         WHERE machine_id = ? AND bo = ? ORDER BY id DESC");
    $st->execute([$mid, $bo]);
    $xoa = $pdo->prepare('DELETE FROM backup_versions WHERE id = ?');
    $thangDaGiu = [];
    foreach ($st->fetchAll() as $i => $r) {
        // id giảm dần → lần đầu gặp một tháng chính là bản cuối cùng của tháng đó.
        $laBanCuoiThang = !isset($thangDaGiu[$r['thang']]);
        $thangDaGiu[$r['thang']] = true;
        if ($i >= $giu && !($giuBanThang && $laBanCuoiThang)) {
            $xoa->execute([(int)$r['id']]);
        }
    }
}

$ip = client_ip();
try {
    $pdo = db();
    gioi_han_tan_suat($pdo, $ip);
    $in = doc_json(NEN_TOI_DA * 2);
    $may = xac_thuc_may($pdo, $in, ['active', 'trial']);

    $bo = $in['bo'] ?? '';
    $hash = $in['hash'] ?? '';
    if (!in_array($bo, ['cu_phap', 'khach_hang'], true) || !la_hex($hash, 64) || !is_string($in['data'] ?? null)) {
        fail(400, 'input');
    }
    $nen = base64_decode($in['data'], true);
    if ($nen === false || strlen($nen) > NEN_TOI_DA) {
        fail(413, 'too_large');
    }
    $raw = @gzdecode($nen, GIAI_NEN_TOI_DA + 1);
    if ($raw === false || strlen($raw) > GIAI_NEN_TOI_DA) {
        fail(413, 'too_large');
    }
    if (!hash_equals($hash, hash('sha256', $raw))) {
        fail(400, 'hash');
    }
    $soDong = json_encode(is_array($in['so_dong'] ?? null) ? $in['so_dong'] : []);
    if (strlen($soDong) > 500) {
        fail(400, 'input');
    }

    $st = $pdo->prepare('SELECT id, hash FROM backup_versions WHERE machine_id = ? AND bo = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$may['machine_id'], $bo]);
    $moiNhat = $st->fetch();
    if ($moiNhat && hash_equals($moiNhat['hash'], $hash)) {
        ghi_log($pdo, $may['machine_id'], $ip, 'backup_da_co');
        ok(['ket_qua' => 'da_co', 'version_id' => (int)$moiNhat['id']]);
    }

    $pdo->prepare('INSERT INTO backup_versions (machine_id, bo, hash, so_dong_json, kich_thuoc, du_lieu, app_version, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$may['machine_id'], $bo, $hash, $soDong, strlen($raw), ma_hoa($nen),
                   str_field($in, 'app_version', 20)]);
    $versionId = (int)$pdo->lastInsertId();
    $daDuyet = $may['status'] === 'active';
    don_phien_ban_cu($pdo, $may['machine_id'], $bo, $daDuyet ? GIU_MOI_NHAT : GIU_MOI_NHAT_DUNG_THU, $daDuyet);
    ghi_log($pdo, $may['machine_id'], $ip, 'backup_da_luu');
    ok(['ket_qua' => 'da_luu', 'version_id' => $versionId]);
} catch (Throwable $e) {
    error_log('BigWIN backup_push: ' . $e->getMessage());
    fail(500, 'server');
}
