<?php
declare(strict_types=1);

// Router cho `php -S` KHI TEST (P01 S1.0) — không dùng ở production (Apache phục vụ trực tiếp).
// Trả false = để built-in server phục vụ tệp như bình thường; thêm 2 đường dẫn chẩn đoán cho test:
//   /__bw_test__/moi-truong  → server đang dùng cấu hình nào (chứng minh trỏ đúng DB test)
//   /__bw_test__/tieu-de     → phản chiếu header client gửi (kiểm client HTTP của harness)
//   /__bw_test__/phien/*, /__bw_test__/tieu-de-bao-mat → gọi lib/security thật (P01 S1.2)
//   /__bw_test__/ajax/{doc,ghi,phan_tich} → endpoint thử của gateway (P03 QĐ2 — không còn trong app/ajax/)

$duongDan = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!str_starts_with((string) $duongDan, '/__bw_test__/')) {
    return false;
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/_cau_hinh_ham.php';
header('Content-Type: application/json; charset=utf-8');

if ($duongDan === '/__bw_test__/moi-truong') {
    echo json_encode(['moi_truong' => cfg('moi_truong'), 'db' => bw_ten_db_test((string) cfg('db_dsn'))]);
} elseif ($duongDan === '/__bw_test__/tieu-de') {
    echo json_encode([
        'csrf' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    ]);
} elseif ($duongDan === '/__bw_test__/phien/bat-dau' || $duongDan === '/__bw_test__/phien/dang-nhap') {
    // Phiên thật (lib/security/PhienBaoMat.php) — cookie & đổi session ID chỉ kiểm được qua web server
    require_once __DIR__ . '/../lib/security/PhienBaoMat.php';
    PhienBaoMat::batDau();
    if ($duongDan === '/__bw_test__/phien/dang-nhap') {
        PhienBaoMat::dangNhap((int) ($_GET['uid'] ?? 0));
    }
    echo json_encode(['sid' => session_id()]);
} elseif (preg_match('#^/__bw_test__/ajax/(doc|ghi|phan_tich)$#', $duongDan, $m) === 1) {
    // Endpoint thử 3 chế độ bản quyền qua gateway THẬT (P03 QĐ2 — thay app/ajax/_ping*.php): P01-T20…T26, P02-T25/T31.
    // doc + {"nem_loi": true} → exception (P01-T24) · ghi + {"dem": true} → đếm 1 tin đối soát (P02-T25).
    require_once __DIR__ . '/../lib/security/AjaxGateway.php';
    $ctx = ajax_khoi_dong($m[1]);
    if ($m[1] === 'doc' && ($ctx['du_lieu']['nem_loi'] ?? false) === true) {
        throw new RuntimeException('Lỗi thử nghiệm của T24 — C:\\duong_dan\\bi_mat.php'); // không được lộ ra phản hồi
    }
    if ($m[1] === 'ghi' && ($ctx['du_lieu']['dem'] ?? false) === true) {
        ajax_dem_han_muc($ctx, 'so_tin_doi_soat');
    }
    ajax_tra($m[1] === 'doc' ? ['uid' => $ctx['user']['id']]
        : ['uid' => $ctx['user']['id'], 'trang_thai' => $ctx['giay_phep']['trang_thai'], 'canh_bao' => $ctx['giay_phep']['canh_bao']]);
} elseif ($duongDan === '/__bw_test__/tieu-de-bao-mat') {
    require_once __DIR__ . '/../lib/security/TieuDeBaoMat.php';
    TieuDeBaoMat::gui(true);
    echo json_encode(['ok' => true]);
} else {
    http_response_code(404);
    echo json_encode(['ok' => false]);
}
return true;
