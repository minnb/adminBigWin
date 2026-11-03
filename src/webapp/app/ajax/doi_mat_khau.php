<?php
declare(strict_types=1);

// Đổi mật khẩu từ modal của khung app (P03 S1.6). ĐỘC LẬP với giấy phép (QĐ5): hết hạn / tạm khoá / chưa có thuê bao vẫn
// đổi được — vẫn bắt buộc phiên, CSRF, nguồn, rate limit. Lỗi đầu vào → 422 kèm loi_truong theo từng ô.

require __DIR__ . '/../../lib/security/AjaxGateway.php';

$ctx = ajax_khoi_dong('doc', ['cho_phep_phai_doi' => true, 'bo_qua_ban_quyen' => true]);
$chuoi = static fn (string $k): string => is_string($ctx['du_lieu'][$k] ?? null) ? $ctx['du_lieu'][$k] : '';
$loi = doi_mat_khau($ctx['user'], $chuoi('mat_khau_cu'), $chuoi('mat_khau_moi'), $chuoi('xac_nhan'));
if ($loi !== []) {
    ajax_loi(422, 'du_lieu_sai', 'Vui lòng sửa các ô được đánh dấu.', ['loi_truong' => $loi]);
}
ajax_tra(['thong_diep' => 'Đã đổi mật khẩu. Các phiên đăng nhập khác đã bị đăng xuất.']);
