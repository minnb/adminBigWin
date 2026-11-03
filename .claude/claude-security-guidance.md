# Security guidance — BigWIN Web (PHP 8.3 thuần, MariaDB, đa thuê bao)

Mô hình đe doạ: nhiều đại lý (tenant) dùng chung một DB; tiền cược thật. Rò dữ liệu chéo tenant hoặc sai tiền là lỗi nghiêm trọng nhất.

## Cô lập tenant (IDOR)
- Mọi truy cập bảng tenant phải qua lớp `extends TenantRepo` với `TenantContext` (tự chèn `user_id`). Báo lỗi nếu thấy SQL viết tay `user_id = ?`, nhận `$userId` từ request/tham số ngoài, hoặc tự `new TenantContext`/dựng ngữ cảnh ngoài `TenantContext::tuPhien()`/`choCli()`/`heThong()`.
- Truy cập theo `id`/`machine_id` từ request phải kiểm quyền sở hữu. `UserRepo`/`ThueBaoRepo` chỉ dùng trong ngữ cảnh admin/hệ thống.
- Không `DELETE` trên `khach_hang`, `tin_nhan_log`, `lich_su_doi_soat`; không UPDATE cột tiền snapshot (xoá mềm, tiền bất biến).

## Web
- `app/ajax/*.php`: câu lệnh đầu tiên phải là `ajax_khoi_dong(cheDo)` (phiên, CSRF, Origin, rate limit, bản quyền). Thay đổi trạng thái chỉ qua POST + CSRF.
- Phiên chỉ qua `lib/security/PhienBaoMat` — không `session_start()` ở nơi khác. Trang admin: `require_login()` / `require_admin()` trước mọi output.
- SQL luôn prepared statement trên PDO `db()`; LIKE dùng `mau_like()`. Không nối chuỗi giá trị người dùng vào SQL.
- Mọi output HTML qua `h()`; giá trị vào JS/thuộc tính Alpine: `json_encode` + `JSON_HEX_*` rồi `h()`.
- Mật khẩu tạm chỉ hiện trên phản hồi của chính thao tác tạo/đặt lại — không vào URL, flash, log.

## Bí mật & hợp đồng
- Không đọc/ghi/log `src/webapp/config.php`, khoá ký (`*.key`). Không chạy `tools/keygen.php` khi chưa được yêu cầu.
- `/api/*`, `lib/api.php`, `lib/crypto.php` là hợp đồng với app desktop đã phát hành — chỉ thay đổi tương thích ngược.

## Lõi tính tiền (`lib/bigwin/`)
- PURE: không DB / I/O / output / đồng hồ trần. Regex qua `bw_re()`; offset qua `bw_finditer()`; chuỗi bằng `mb_*`.
- Làm tròn tiền bằng `bw_py_round()` (KHÔNG `round(..., PHP_ROUND_HALF_EVEN)` — lệch Python). Không `array_unique`/`array_flip` trên số dạng chuỗi ("05").
- Không bao giờ sửa `tests/fixtures/*.json` để test qua.
