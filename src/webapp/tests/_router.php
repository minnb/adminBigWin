<?php
declare(strict_types=1);

// Router cho `php -S` KHI TEST (P01 S1.0) — không dùng ở production (Apache phục vụ trực tiếp).
// Trả false = để built-in server phục vụ tệp như bình thường; thêm 2 đường dẫn chẩn đoán cho test:
//   /__bw_test__/moi-truong  → server đang dùng cấu hình nào (chứng minh trỏ đúng DB test)
//   /__bw_test__/tieu-de     → phản chiếu header client gửi (kiểm client HTTP của harness)
//   /__bw_test__/phien/*, /__bw_test__/tieu-de-bao-mat → gọi lib/security thật (P01 S1.2)

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
} elseif ($duongDan === '/__bw_test__/tieu-de-bao-mat') {
    require_once __DIR__ . '/../lib/security/TieuDeBaoMat.php';
    TieuDeBaoMat::gui(true);
    echo json_encode(['ok' => true]);
} else {
    http_response_code(404);
    echo json_encode(['ok' => false]);
}
return true;
