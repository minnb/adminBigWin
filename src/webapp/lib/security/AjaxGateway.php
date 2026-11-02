<?php
declare(strict_types=1);

// Cổng DUY NHẤT cho mọi app/ajax/*.php (kế hoạch tổng §5.3c). Câu lệnh đầu của endpoint:
//   $ctx = ajax_khoi_dong('doc'|'ghi'|'phan_tich' [, ['get' => true, 'toi_da_byte' => n, 'cho_phep_phai_doi' => true]]);
// Thứ tự: lỗi JSON → tiêu đề → phương thức → Content-Type/kích thước/JSON → phiên → người dùng → CSRF + nguồn
// → (P02: bản quyền) → rate limit. Kiểm đăng nhập TRƯỚC CSRF: phiên hết hạn/đã đăng xuất trả 401 (cần đăng nhập
// lại) thay vì 403 khó hiểu; đã đăng nhập mà sai token vẫn 403. Mã lỗi: 401 chưa đăng nhập · 403 CSRF/không quyền · 405 sai phương thức
// · 422 dữ liệu sai · 429 quá tần suất · 500 lỗi hệ thống (chỉ trả mã tham chiếu, chi tiết ở ghi_loi()).

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/GioiHanTanSuat.php';

const AJAX_BODY_TOI_DA = 65536;
const AJAX_GIOI_HAN_PHUT = 120;

function ajax_tra(array $duLieu = []): never
{
    echo json_encode(['ok' => true] + $duLieu, JSON_UNESCAPED_UNICODE);
    exit;
}

function ajax_loi(int $ma, string $loi, string $thongDiep, array $them = []): never
{
    http_response_code($ma);
    echo json_encode(['ok' => false, 'ma' => $ma, 'loi' => $loi, 'thong_diep' => $thongDiep] + $them, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Trả ['user' => người dùng đã xác thực, 'du_lieu' => body JSON đã giải mã]; mọi vi phạm dừng request bằng JSON lỗi. */
function ajax_khoi_dong(string $cheDo, array $tuyChon = []): array
{
    set_exception_handler(static function (Throwable $e): void {
        $ref = ghi_loi($e);
        ajax_loi(500, 'loi_he_thong', "Lỗi hệ thống — báo quản trị mã $ref.", ['ma_tham_chieu' => $ref]);
    });
    if (!in_array($cheDo, ['doc', 'ghi', 'phan_tich'], true)) {
        throw new LogicException("ajax_khoi_dong: chế độ '$cheDo' không hợp lệ");
    }
    header('Content-Type: application/json; charset=utf-8');
    TieuDeBaoMat::gui(true);

    $laGet = !empty($tuyChon['get']);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== ($laGet ? 'GET' : 'POST')) {
        header('Allow: ' . ($laGet ? 'GET' : 'POST'));
        ajax_loi(405, 'sai_phuong_thuc', 'Phương thức không được hỗ trợ.');
    }
    $duLieu = [];
    if (!$laGet) {
        if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            ajax_loi(422, 'sai_dinh_dang', 'Yêu cầu phải gửi dạng JSON.');
        }
        $toiDa = (int) ($tuyChon['toi_da_byte'] ?? AJAX_BODY_TOI_DA);
        $than = (string) file_get_contents('php://input', false, null, 0, $toiDa + 1);
        if (strlen($than) > $toiDa) {
            ajax_loi(422, 'qua_lon', 'Dữ liệu gửi lên quá lớn.');
        }
        $duLieu = $than === '' ? [] : json_decode($than, true);
        if (!is_array($duLieu)) {
            ajax_loi(422, 'json_sai', 'Dữ liệu JSON không hợp lệ.');
        }
    }

    $user = nguoi_dung_hien_tai();
    if ($user === null) {
        ajax_loi(401, 'chua_dang_nhap', 'Bạn cần đăng nhập lại.');
    }
    if (!$laGet && !(Csrf::nguonHopLe($_SERVER) && Csrf::hopLe($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
        ajax_loi(403, 'csrf', 'Phiên làm việc không hợp lệ — tải lại trang rồi thử lại.');
    }
    if ($user['phai_doi_mat_khau'] === 1 && empty($tuyChon['cho_phep_phai_doi'])) {
        ajax_loi(403, 'phai_doi_mat_khau', 'Bạn cần đổi mật khẩu trước khi dùng hệ thống.');
    }
    // P02 (S1.5): GiayPhepGuard::kiemTra(TenantContext, $cheDo) — tạm cho qua mọi chế độ.
    if (!GioiHanTanSuat::cho('ajax:' . $user['id'], AJAX_GIOI_HAN_PHUT, 60)) {
        ajax_loi(429, 'qua_tan_suat', 'Thao tác quá nhanh — thử lại sau ít giây.');
    }
    return ['user' => $user, 'du_lieu' => $duLieu];
}
