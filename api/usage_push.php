<?php
declare(strict_types=1);

/**
 * POST /api/usage_push.php — nhật ký "Tổng hợp kết quả" người dùng đã xem trên form Telegram.
 *
 * Vào : {machine_id, device_secret, items: [{id_cuc_bo, ngay "dd-mm-yyyy", khach_hang_id,
 *        ten_khach, rows, tong, so_trung, xem_luc "Y-m-d H:i:s", nguon}]}  (≤ 50 mục)
 * Ra  : {da_nhan: [id_cuc_bo…], bi_bo: n}
 * Ghi đè bản mới nhất theo (máy, ngày, khách) + cộng so_lan_xem (Q3). Mục hỏng vẫn trả trong
 * da_nhan (để app xoá khỏi hàng đợi, không gửi lại mãi) và được đếm ở bi_bo.
 */

require __DIR__ . '/../lib/api.php';

const MUC_TOI_DA = 50;
const PAYLOAD_TOI_DA = 200 * 1024;

/** Chuẩn hoá 1 mục; null = hỏng. */
function chuan_hoa_muc(array $it): ?array
{
    $d = DateTime::createFromFormat('!d-m-Y', (string)($it['ngay'] ?? ''));
    $xem = DateTime::createFromFormat('Y-m-d H:i:s', (string)($it['xem_luc'] ?? ''));
    $khId = $it['khach_hang_id'] ?? null;
    if (!$d || !$xem || !is_int($khId) || $khId < 1 || $khId > 2147483647) {
        return null;
    }
    $payload = json_encode([
        'nguon' => str_field($it, 'nguon', 30),
        'rows' => is_array($it['rows'] ?? null) ? $it['rows'] : [],
        'tong' => is_array($it['tong'] ?? null) ? $it['tong'] : [],
        'so_trung' => is_array($it['so_trung'] ?? null) ? $it['so_trung'] : [],
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false || strlen($payload) > PAYLOAD_TOI_DA) {
        return null;
    }
    $thanhTien = $it['tong']['thanh_tien'] ?? 0;
    return [
        'ngay' => $d->format('Y-m-d'), 'khach_hang_id' => $it['khach_hang_id'],
        'ten_khach' => str_field($it, 'ten_khach', 150), 'payload' => $payload,
        'thanh_tien' => is_numeric($thanhTien) ? (int)round((float)$thanhTien) : 0,
        'xem_luc' => $xem->format('Y-m-d H:i:s'),
    ];
}

$ip = client_ip();
try {
    $pdo = db();
    gioi_han_tan_suat($pdo, $ip);
    $in = doc_json(2 * 1024 * 1024);
    $may = xac_thuc_may($pdo, $in, ['active', 'trial']);
    $items = $in['items'] ?? null;
    if (!is_array($items) || count($items) > MUC_TOI_DA) {
        fail(400, 'input');
    }

    $ghi = $pdo->prepare(
        'INSERT INTO usage_summaries (machine_id, ngay, khach_hang_id, ten_khach, payload_json,
                                      tong_thanh_tien, so_lan_xem, xem_dau, xem_cuoi)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
         ON DUPLICATE KEY UPDATE
            -- Chỉ ghi đè nội dung khi lần xem tới MỚI HƠN lần đang lưu: lần xem cũ gửi lại muộn
            -- (sau lỗi mạng) không được đè thành tiền mới. xem_cuoi PHẢI cập nhật CUỐI CÙNG —
            -- MySQL gán từ trái sang phải, các điều kiện trên cần xem_cuoi cũ.
            ten_khach       = IF(VALUES(xem_cuoi) >= xem_cuoi, VALUES(ten_khach), ten_khach),
            payload_json    = IF(VALUES(xem_cuoi) >= xem_cuoi, VALUES(payload_json), payload_json),
            tong_thanh_tien = IF(VALUES(xem_cuoi) >= xem_cuoi, VALUES(tong_thanh_tien), tong_thanh_tien),
            so_lan_xem      = so_lan_xem + 1,
            xem_dau         = LEAST(xem_dau, VALUES(xem_dau)),
            xem_cuoi        = GREATEST(xem_cuoi, VALUES(xem_cuoi))'
    );
    $daNhan = [];
    $biBo = 0;
    $soLoiGhi = 0;
    $soThuGhi = 0;
    foreach ($items as $it) {
        $id = is_array($it) ? ($it['id_cuc_bo'] ?? null) : null;
        if (!is_int($id)) {
            $biBo++;
            continue;  // không có id thì app cũng không xoá được — bỏ qua
        }
        $daNhan[] = $id;
        $m = chuan_hoa_muc($it);
        if ($m === null) {
            $biBo++;
            continue;
        }
        // Bọc TỪNG mục: một mục hỏng ở bước ghi (vd khach_hang_id vượt INT) không được làm cả
        // lô trả 500 — app sẽ gửi lại đúng lô đó mãi và chặn mọi nhật ký phía sau.
        $soThuGhi++;
        try {
            $ghi->execute([$may['machine_id'], $m['ngay'], $m['khach_hang_id'], $m['ten_khach'],
                           $m['payload'], $m['thanh_tien'], $m['xem_luc'], $m['xem_luc']]);
        } catch (PDOException $e) {
            error_log('BigWIN usage_push item ' . $id . ': ' . $e->getMessage());
            $soLoiGhi++;
            $biBo++;
        }
    }
    // MỌI mục đều lỗi ghi → nhiều khả năng là DB đang hỏng chứ không phải dữ liệu rác:
    // trả 500, KHÔNG xác nhận, để app giữ hàng đợi và gửi lại sau.
    if ($soThuGhi > 0 && $soLoiGhi === $soThuGhi) {
        throw new RuntimeException("all $soThuGhi items failed to write");
    }
    ghi_log($pdo, $may['machine_id'], $ip, 'usage_push');
    ok(['da_nhan' => $daNhan, 'bi_bo' => $biBo]);
} catch (Throwable $e) {
    error_log('BigWIN usage_push: ' . $e->getMessage());
    fail(500, 'server');
}
